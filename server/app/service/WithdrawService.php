<?php
declare (strict_types = 1);

namespace app\service;

use app\model\Agent;
use app\model\Wallet;
use app\model\WalletTransaction;
use think\facade\Log;
use think\facade\Db;

class WithdrawService
{
    const STATUS_PENDING = 1;
    const STATUS_APPROVED = 2;
    const STATUS_REJECTED = 3;
    const STATUS_PROCESSING = 4;
    const STATUS_COMPLETED = 5;

    const FEE_RATE = 0.03;
    const MIN_AMOUNT = 1;

    public function applyWithdraw(int $userId, float $amount, string $account, int $walletType = 2): array
    {
        if ($amount < self::MIN_AMOUNT) {
            return ['success' => false, 'message' => '最低提现金额为' . self::MIN_AMOUNT . '元'];
        }

        $wallet = Wallet::where('user_id', $userId)->where('type', $walletType)->find();
        if (!$wallet) {
            return ['success' => false, 'message' => '钱包不存在'];
        }

        $available = bcsub(strval($wallet->balance), strval($wallet->frozen), 2);
        if (floatval($available) < $amount) {
            return ['success' => false, 'message' => '可用余额不足'];
        }

        $fee = bcmul(strval($amount), strval(self::FEE_RATE), 2);
        $actualAmount = bcsub(strval($amount), $fee, 2);

        if (floatval($actualAmount) <= 0) {
            return ['success' => false, 'message' => '提现金额过低'];
        }

        Db::startTrans();
        try {
            $wallet->frozen = bcadd(strval($wallet->frozen), strval($amount), 2);
            $wallet->save();

            $withdraw = Db::name('withdraws')->insertGetId([
                'user_id' => $userId,
                'wallet_type' => $walletType,
                'amount' => $amount,
                'fee' => $fee,
                'actual_amount' => $actualAmount,
                'account' => $account,
                'status' => self::STATUS_PENDING,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $transaction = new WalletTransaction();
            $transaction->wallet_id = $wallet->id;
            $transaction->type = 4;
            $transaction->amount = $amount;
            $transaction->related_order = 'W' . $withdraw;
            $transaction->balance_after = $wallet->frozen;
            $transaction->remark = '提现申请冻结';
            $transaction->save();

            Db::commit();

            return [
                'success' => true,
                'withdraw_id' => $withdraw,
                'amount' => $amount,
                'fee' => $fee,
                'actual_amount' => $actualAmount,
                'message' => '提现申请已提交',
            ];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('apply_withdraw_error', ['user_id' => $userId, 'msg' => $e->getMessage()]);
            return ['success' => false, 'message' => '申请失败：' . $e->getMessage()];
        }
    }

    public function auditWithdraw(int $withdrawId, bool $pass, string $reason = ''): array
    {
        $withdraw = Db::name('withdraws')->where('id', $withdrawId)->find();
        if (!$withdraw) {
            return ['success' => false, 'message' => '提现记录不存在'];
        }

        if ($withdraw['status'] != self::STATUS_PENDING) {
            return ['success' => false, 'message' => '该提现已处理'];
        }

        Db::startTrans();
        try {
            $wallet = Wallet::where('user_id', $withdraw['user_id'])
                ->where('type', $withdraw['wallet_type'])
                ->lock(true)
                ->find();

            if ($pass) {
                Db::name('withdraws')->where('id', $withdrawId)->update([
                    'status' => self::STATUS_APPROVED,
                    'audit_time' => date('Y-m-d H:i:s'),
                    'audit_remark' => $reason,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                if ($wallet) {
                    $wallet->frozen = bcsub(strval($wallet->frozen), strval($withdraw['amount']), 2);
                    $wallet->balance = bcsub(strval($wallet->balance), strval($withdraw['amount']), 2);
                    $wallet->save();

                    $transaction = new WalletTransaction();
                    $transaction->wallet_id = $wallet->id;
                    $transaction->type = 3;
                    $transaction->amount = $withdraw['amount'];
                    $transaction->related_order = 'W' . $withdrawId;
                    $transaction->balance_after = $wallet->balance;
                    $transaction->remark = '提现已打款';
                    $transaction->settle_status = 1;
                    $transaction->save();
                }
            } else {
                Db::name('withdraws')->where('id', $withdrawId)->update([
                    'status' => self::STATUS_REJECTED,
                    'audit_time' => date('Y-m-d H:i:s'),
                    'audit_remark' => $reason,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                if ($wallet) {
                    $wallet->frozen = bcsub(strval($wallet->frozen), strval($withdraw['amount']), 2);
                    $wallet->save();

                    $transaction = new WalletTransaction();
                    $transaction->wallet_id = $wallet->id;
                    $transaction->type = 5;
                    $transaction->amount = $withdraw['amount'];
                    $transaction->related_order = 'W' . $withdrawId;
                    $transaction->balance_after = $wallet->balance;
                    $transaction->remark = '提现驳回解冻';
                    $transaction->settle_status = 1;
                    $transaction->save();
                }
            }

            Db::commit();
            return ['success' => true, 'message' => $pass ? '审核通过' : '已驳回'];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('audit_withdraw_error', ['withdraw_id' => $withdrawId, 'msg' => $e->getMessage()]);
            return ['success' => false, 'message' => '审核失败：' . $e->getMessage()];
        }
    }

    public function getWithdrawList(int $userId, int $walletType = 2, int $page = 1, int $pageSize = 10): array
    {
        $query = Db::name('withdraws')
            ->where('user_id', $userId)
            ->where('wallet_type', $walletType)
            ->order('id', 'desc');

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $item) {
            $items[] = [
                'id' => $item['id'],
                'amount' => $item['amount'],
                'fee' => $item['fee'],
                'actual_amount' => $item['actual_amount'],
                'account' => $item['account'],
                'status' => $item['status'],
                'status_text' => $this->getStatusText($item['status']),
                'audit_remark' => $item['audit_remark'] ?? '',
                'created_at' => $item['created_at'],
                'audit_time' => $item['audit_time'] ?? '',
            ];
        }

        return [
            'list' => $items,
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'pageSize' => $list->listRows(),
        ];
    }

    protected function getStatusText(int $status): string
    {
        $statuses = [
            1 => '待审核',
            2 => '审核通过',
            3 => '已驳回',
            4 => '处理中',
            5 => '已完成',
        ];
        return $statuses[$status] ?? '未知';
    }
}
