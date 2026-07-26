<?php
declare (strict_types = 1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;
use app\model\Merchant;

class QuotaMiddleware
{
    protected $whitelist = [
        'api/merchant/profile',
        'api/merchant/package',
        'api/merchant/wallet',
    ];

    public function handle(Request $request, Closure $next, string $quotaType = ''): Response
    {
        $roleType = $request->role_type ?? 0;
        if ($roleType == 1 || $roleType == 2) {
            return $next($request);
        }

        if ($this->isWhitelist($request)) {
            return $next($request);
        }

        $merchantId = $request->merchant_id ?? 0;
        if (!$merchantId) {
            return $this->forbidden('商户信息不存在');
        }

        $merchant = Merchant::with(['package'])->find($merchantId);
        if (!$merchant) {
            return $this->forbidden('商户信息不存在');
        }

        if ($quotaType == 'app') {
            if ($merchant->remaining_apps == 0) {
                return $this->forbidden('应用数量已达上限，请升级套餐');
            }
        } elseif ($quotaType == 'card') {
            if ($merchant->remaining_cards == 0) {
                return $this->forbidden('卡密配额已用完，请升级套餐');
            }
        }

        $request->merchant = $merchant;

        return $next($request);
    }

    protected function isWhitelist(Request $request): bool
    {
        $path = strtolower($request->pathinfo());
        foreach ($this->whitelist as $item) {
            if (str_starts_with($path, $item)) {
                return true;
            }
        }
        return false;
    }

    protected function forbidden(string $message = '无权限访问'): Response
    {
        return json([
            'code'    => 403,
            'message' => $message,
            'data'    => null,
        ])->code(403);
    }
}
