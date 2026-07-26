<?php
declare (strict_types = 1);

namespace app\controller\shop;

use app\BaseController;
use app\model\Merchant;
use app\model\ShopProduct;
use app\model\Order;
use app\service\PaymentService;
use app\service\CardDeliveryService;
use app\service\PurchaseLimitService;
use think\Request;
use think\facade\Log;

class ShopController extends BaseController
{
    public function index(Request $request, $merchantNo)
    {
        $merchant = Merchant::where('merchant_no', $merchantNo)->find();
        if (!$merchant) {
            return error('店铺不存在', 404);
        }

        $shopConfig = $this->getShopConfig($merchant);

        $products = ShopProduct::where('merchant_id', $merchant->id)
            ->where('status', ShopProduct::STATUS_ONLINE)
            ->order('id', 'desc')
            ->limit(50)
            ->select();

        $productList = [];
        foreach ($products as $product) {
            $productList[] = [
                'id' => $product->id,
                'name' => $product->name,
                'image' => $product->image,
                'price' => $product->price,
                'stock' => $product->stock,
                'category' => $product->category,
                'description' => $product->description,
            ];
        }

        return success([
            'shop' => $shopConfig,
            'products' => $productList,
        ], '获取成功');
    }

    public function products(Request $request, $merchantNo)
    {
        $merchant = Merchant::where('merchant_no', $merchantNo)->find();
        if (!$merchant) {
            return error('店铺不存在', 404);
        }

        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 20);
        $category = $request->param('category', '');
        $keyword = $request->param('keyword', '');

        $query = ShopProduct::where('merchant_id', $merchant->id)
            ->where('status', ShopProduct::STATUS_ONLINE)
            ->order('id', 'desc');

        if ($category) {
            $query->where('category', $category);
        }

        if ($keyword) {
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $product) {
            $items[] = [
                'id' => $product->id,
                'name' => $product->name,
                'image' => $product->image,
                'price' => $product->price,
                'stock' => $product->stock,
                'category' => $product->category,
                'description' => mb_substr($product->description, 0, 100),
            ];
        }

        return success([
            'list' => $items,
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'pageSize' => $list->listRows(),
        ], '获取成功');
    }

    public function productDetail(Request $request, $merchantNo, $id)
    {
        $merchant = Merchant::where('merchant_no', $merchantNo)->find();
        if (!$merchant) {
            return error('店铺不存在', 404);
        }

        $product = ShopProduct::where('id', $id)
            ->where('merchant_id', $merchant->id)
            ->where('status', ShopProduct::STATUS_ONLINE)
            ->find();

        if (!$product) {
            return error('商品不存在', 404);
        }

        return success([
            'id' => $product->id,
            'name' => $product->name,
            'image' => $product->image,
            'description' => $product->description,
            'price' => $product->price,
            'stock' => $product->stock,
            'category' => $product->category,
            'limit_per_user' => $product->limit_per_user,
            'limit_per_ip' => $product->limit_per_ip,
            'limit_per_device' => $product->limit_per_device,
        ], '获取成功');
    }

    public function createOrder(Request $request, $merchantNo)
    {
        $merchant = Merchant::where('merchant_no', $merchantNo)->find();
        if (!$merchant) {
            return error('店铺不存在', 404);
        }

        $productId = intval($request->param('product_id', 0));
        $quantity = intval($request->param('quantity', 1));
        $email = $request->param('email', '');
        $payChannel = $request->param('pay_channel', 'caihong');
        $payType = $request->param('pay_type', 'alipay');
        $userId = intval($request->param('user_id', 0));
        $agentId = intval($request->param('agent_id', 0));

        if ($productId <= 0) {
            return error('请选择商品', 400);
        }

        if ($quantity <= 0) {
            return error('购买数量无效', 400);
        }

        if (empty($email)) {
            return error('请填写邮箱', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return error('邮箱格式不正确', 400);
        }

        $product = ShopProduct::where('id', $productId)
            ->where('merchant_id', $merchant->id)
            ->where('status', ShopProduct::STATUS_ONLINE)
            ->find();

        if (!$product) {
            return error('商品不存在或已下架', 404);
        }

        if ($product->stock < $quantity) {
            return error('库存不足', 400);
        }

        $purchaseLimitService = new PurchaseLimitService();
        $limitResult = $purchaseLimitService->checkLimit(
            $productId,
            $userId,
            $request->ip(),
            ''
        );

        if (!$limitResult['success']) {
            return error($limitResult['message'], 400);
        }

        $amount = bcmul(strval($product->price), strval($quantity), 2);

        $paymentService = new PaymentService();
        $result = $paymentService->createPaymentOrder(
            Order::TYPE_SHOP,
            $userId > 0 ? $userId : 0,
            $merchant->id,
            $productId,
            floatval($amount),
            $payChannel,
            [
                'product_name' => $product->name,
                'pay_type' => $payType,
                'email' => $email,
                'quantity' => $quantity,
                'agent_id' => $agentId,
            ]
        );

        if ($result['success']) {
            return success($result, '订单创建成功');
        } else {
            return error($result['message'] ?? '创建失败', 500);
        }
    }

    public function queryOrder(Request $request)
    {
        $orderNo = $request->param('order_no', '');
        $email = $request->param('email', '');

        if (empty($orderNo) || empty($email)) {
            return error('订单号和邮箱不能为空', 400);
        }

        $order = Order::where('order_no', $orderNo)
            ->where('email', $email)
            ->find();

        if (!$order) {
            return error('订单不存在', 404);
        }

        $cardInfo = null;
        if ($order->isPaid()) {
            $cardDeliveryService = new CardDeliveryService();
            $cardResult = $cardDeliveryService->getCardInfoByOrder($order->id);
            if ($cardResult['success']) {
                $cardInfo = $cardResult;
            }
        }

        return success([
            'order_no' => $order->order_no,
            'type' => $order->type,
            'type_text' => $order->type_text,
            'amount' => $order->amount,
            'pay_status' => $order->pay_status,
            'status_text' => $order->status_text,
            'pay_time' => $order->pay_time,
            'email' => $order->email,
            'card_info' => $cardInfo,
            'created_at' => $order->created_at,
        ], '查询成功');
    }

    protected function getShopConfig(Merchant $merchant): array
    {
        return [
            'merchant_no' => $merchant->merchant_no,
            'shop_name' => $merchant->merchant_name,
            'shop_logo' => '',
            'shop_banner' => '',
            'shop_theme' => 'default',
            'shop_notice' => '',
            'contact_info' => '',
            'shop_status' => $merchant->status,
        ];
    }
}
