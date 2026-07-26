<?php
declare (strict_types = 1);

namespace app\service;

use app\model\Order;
use app\model\Card;
use app\model\ShopProduct;
use think\facade\Log;
use think\facade\Db;

class CardDeliveryService
{
    public function deliverCard(int $orderId): array
    {
        $order = Order::where('id', $orderId)->find();
        if (!$order) {
            return ['success' => false, 'message' => '订单不存在'];
        }

        if (!$order->isPaid()) {
            return ['success' => false, 'message' => '订单未支付'];
        }

        if ($order->card_id > 0) {
            return ['success' => true, 'message' => '已发卡', 'card_id' => $order->card_id];
        }

        $product = ShopProduct::where('id', $order->product_id)->find();
        if (!$product) {
            return ['success' => false, 'message' => '商品不存在'];
        }

        Db::startTrans();
        try {
            $card = Card::where('app_id', $product->app_id)
                ->where('merchant_id', $product->merchant_id)
                ->where('status', Card::STATUS_UNUSED)
                ->order('id', 'asc')
                ->lock(true)
                ->find();

            if (!$card) {
                Db::rollback();
                return ['success' => false, 'message' => '库存不足'];
            }

            $card->status = Card::STATUS_SOLD;
            $card->sold_time = date('Y-m-d H:i:s');
            $card->order_id = $order->id;
            $card->save();

            $order->card_id = $card->id;
            $order->save();

            $product->stock = max(0, $product->stock - 1);
            $product->save();

            Db::commit();

            if (!empty($order->email)) {
                try {
                    $this->sendCardEmail($order->email, $card->card_no, $product->name);
                } catch (\Exception $e) {
                    Log::error('send_card_email_error', ['order_id' => $orderId, 'msg' => $e->getMessage()]);
                }
            }

            return [
                'success' => true,
                'card_id' => $card->id,
                'card_no' => $card->card_no,
                'message' => '发卡成功',
            ];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('deliver_card_error', ['order_id' => $orderId, 'msg' => $e->getMessage()]);
            return ['success' => false, 'message' => '发卡失败：' . $e->getMessage()];
        }
    }

    public function sendCardEmail(string $email, string $cardNo, string $productName): bool
    {
        $subject = '【卡密发货通知】您购买的' . $productName . '已发货';
        $body = "尊敬的客户：\n\n";
        $body .= "您购买的商品【{$productName}】已支付成功，卡密信息如下：\n\n";
        $body .= "卡密：{$cardNo}\n\n";
        $body .= "请妥善保管您的卡密信息。\n\n";
        $body .= "感谢您的购买！\n";

        $headers = "From: noreply@example.com\r\n";
        $headers .= "Content-Type: text/plain; charset=utf-8\r\n";

        return @mail($email, $subject, $body, $headers);
    }

    public function getCardInfoByOrder(int $orderId): array
    {
        $order = Order::where('id', $orderId)->with('card,product')->find();
        if (!$order) {
            return ['success' => false, 'message' => '订单不存在'];
        }

        if (!$order->isPaid()) {
            return ['success' => false, 'message' => '订单未支付'];
        }

        if (!$order->card || !$order->card->card_no) {
            return ['success' => false, 'message' => '卡密信息不存在'];
        }

        return [
            'success' => true,
            'card_no' => $order->card->card_no,
            'product_name' => $order->product ? $order->product->name : '',
            'order_no' => $order->order_no,
            'pay_time' => $order->pay_time,
        ];
    }
}
