<?php
declare (strict_types = 1);

namespace app\controller\api;

use app\BaseController;
use app\service\PaymentService;
use think\Request;
use think\facade\Log;

class PayNotifyController extends BaseController
{
    public function caihong(Request $request)
    {
        $data = $request->param();

        Log::info('pay_notify_caihong_received', $data);

        $paymentService = new PaymentService();
        $result = $paymentService->handleNotify('caihong', $data);

        if ($result) {
            return 'success';
        }

        return 'fail';
    }
}
