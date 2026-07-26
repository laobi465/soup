<?php
declare (strict_types = 1);

namespace app\controller\api;

use app\BaseController;
use app\model\User;
use app\model\Merchant;
use app\model\Agent;
use app\model\Wallet;
use app\model\Package;
use app\library\Bcrypt;
use app\library\Random;
use app\service\RiskControlService;
use think\Request;
use think\facade\Db;

class RegisterController extends BaseController
{
    public function merchant(Request $request)
    {
        $ip = $request->ip();

        if (!RiskControlService::checkRegisterLimit($ip)) {
            return error('今日注册次数已达上限，请稍后再试', 429);
        }

        $username      = $request->param('username', '');
        $password      = $request->param('password', '');
        $email         = $request->param('email', '');
        $merchantName  = $request->param('merchant_name', '');
        $inviteCode    = $request->param('invite_code', '');

        if (empty($username) || empty($password) || empty($email) || empty($merchantName)) {
            return error('请填写完整注册信息', 400);
        }

        if (strlen($username) < 4 || strlen($username) > 20) {
            return error('用户名长度需在4-20个字符之间', 400);
        }

        if (strlen($password) < 6 || strlen($password) > 32) {
            return error('密码长度需在6-32个字符之间', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return error('邮箱格式不正确', 400);
        }

        if (strlen($merchantName) < 2 || strlen($merchantName) > 50) {
            return error('商户名称长度需在2-50个字符之间', 400);
        }

        $exists = User::where('username', $username)->find();
        if ($exists) {
            return error('用户名已存在', 400);
        }

        $exists = User::where('email', $email)->find();
        if ($exists) {
            return error('邮箱已被注册', 400);
        }

        $inviteInfo = $this->parseInviteCode($inviteCode);
        if ($inviteCode && !$inviteInfo) {
            return error('邀请码无效或已过期', 400);
        }

        $agentUserId = $inviteInfo['user_id'] ?? null;
        $agentId = $inviteInfo['agent_id'] ?? 0;
        $merchantId = $inviteInfo['merchant_id'] ?? 0;
        $agentLevel = $inviteInfo['level'] ?? 0;

        Db::startTrans();
        try {
            $passwordHash = Bcrypt::hash($password);

            $user = new User();
            $user->username        = $username;
            $user->password_hash   = $passwordHash;
            $user->email           = $email;
            $user->role_type       = 3;
            $user->parent_id       = $agentUserId ?? 0;
            $user->status          = 1;
            $user->login_fail_count = 0;
            $user->created_at      = date('Y-m-d H:i:s');
            $user->updated_at      = date('Y-m-d H:i:s');
            $user->save();

            $defaultPackage = Package::where('name', '青铜')->find();
            $packageId = $defaultPackage ? $defaultPackage->id : 0;
            $appQuota  = $defaultPackage ? $defaultPackage->app_limit : 1;
            $cardQuota = $defaultPackage ? $defaultPackage->card_limit : 1000;

            $merchant = new Merchant();
            $merchant->user_id           = $user->id;
            $merchant->merchant_no       = Random::merchantNo();
            $merchant->merchant_name     = $merchantName;
            $merchant->package_id        = $packageId;
            $merchant->balance           = '0.00';
            $merchant->frozen_balance    = '0.00';
            $merchant->app_quota         = $appQuota;
            $merchant->card_quota        = $cardQuota;
            $merchant->card_used         = 0;
            $merchant->agent_invite_code = Random::inviteCode();
            $merchant->status            = 1;
            $merchant->created_at        = date('Y-m-d H:i:s');
            $merchant->updated_at        = date('Y-m-d H:i:s');
            $merchant->save();

            $wallet = new Wallet();
            $wallet->user_id    = $user->id;
            $wallet->type       = 1;
            $wallet->balance    = '0.00';
            $wallet->frozen     = '0.00';
            $wallet->created_at = date('Y-m-d H:i:s');
            $wallet->updated_at = date('Y-m-d H:i:s');
            $wallet->save();

            $agentIdCreated = 0;
            if (!empty($inviteCode)) {
                $agentWallet = new Wallet();
                $agentWallet->user_id    = $user->id;
                $agentWallet->type       = 2;
                $agentWallet->balance    = '0.00';
                $agentWallet->frozen     = '0.00';
                $agentWallet->created_at = date('Y-m-d H:i:s');
                $agentWallet->updated_at = date('Y-m-d H:i:s');
                $agentWallet->save();

                $agent = new Agent();
                $agent->user_id = $user->id;
                $agent->merchant_id = $merchantId > 0 ? $merchantId : $merchant->id;
                $agent->parent_agent_id = $agentId;
                $agent->level = $agentLevel + 1;
                $agent->invite_code = Random::inviteCode();
                $agent->invite_url = '';
                $agent->purchase_price_rate = '1.00';
                $agent->commission_rate = '0.10';
                $agent->total_earnings = '0.00';
                $agent->available_balance = '0.00';
                $agent->frozen_balance = '0.00';
                $agent->status = 1;
                $agent->save();
                $agentIdCreated = $agent->id;
            }

            Db::commit();

            RiskControlService::recordRegister($ip);

            return success([
                'user_id'          => $user->id,
                'username'         => $user->username,
                'merchant_id'      => $merchant->id,
                'merchant_no'      => $merchant->merchant_no,
                'merchant_name'    => $merchant->merchant_name,
                'agent_invite_code' => $merchant->agent_invite_code,
                'agent_id'         => $agentIdCreated,
            ], '注册成功');
        } catch (\Exception $e) {
            Db::rollback();
            return error('注册失败：' . $e->getMessage(), 500);
        }
    }

    protected function parseInviteCode(string $inviteCode): ?array
    {
        if (empty($inviteCode)) {
            return null;
        }

        $merchant = Merchant::where('agent_invite_code', $inviteCode)->find();
        if ($merchant) {
            return [
                'type' => 'merchant',
                'merchant_id' => $merchant->id,
                'user_id' => $merchant->user_id,
                'agent_id' => 0,
                'level' => 0,
            ];
        }

        $agent = Agent::where('invite_code', $inviteCode)->find();
        if ($agent && $agent->isNormal()) {
            if ($agent->level >= Agent::LEVEL_THREE) {
                return null;
            }

            return [
                'type' => 'agent',
                'merchant_id' => $agent->merchant_id,
                'user_id' => $agent->user_id,
                'agent_id' => $agent->id,
                'level' => $agent->level,
            ];
        }

        return null;
    }
}
