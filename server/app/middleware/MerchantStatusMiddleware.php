<?php
declare (strict_types = 1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;
use app\model\Merchant;

class MerchantStatusMiddleware
{
    protected $whitelist = [
        'api/merchant/auth',
    ];

    public function handle(Request $request, Closure $next): Response
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
            return $next($request);
        }

        $merchant = Merchant::find($merchantId);
        if (!$merchant) {
            return $this->forbidden('商户信息不存在');
        }

        if ($merchant->status != 1) {
            return $this->forbidden('商户已被禁用');
        }

        if ($merchant->isPackageExpired()) {
            return $this->forbidden('商户套餐已过期');
        }

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
