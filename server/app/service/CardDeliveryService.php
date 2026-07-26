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
    public function deliverCard(int $orderId, int $quantity = 1): array
    {
        $order = Order::where('id', $orderId)->find();
        if (!$order) {
            return ['success' => false, 'message' => '订单不存在'];
        }

        if (!$order->isPaid()) {
            return ['success' => false, 'message' => '订单未支付'];
        }

        if ($order->card_id > 0) {
            $cards = Card::where('order_id', $order->id)->select();
            $cardIds = [];
            $cardNos = [];
            foreach ($cards as $card) {
                $cardIds[] = $card->id;
                $cardNos[] = $card->getPlainCardNo();
            }
            return [
                'success' => true,
                'message' => '已发卡',
                'card_id' => $order->card_id,
                'card_ids' => $cardIds,
                'card_nos' => $cardNos,
            ];
        }

        $product = ShopProduct::where('id', $order->product_id)->find();
        if (!$product) {
            return ['success' => false, 'message' => '商品不存在'];
        }

        if ($quantity <= 0) {
            $quantity = 1;
        }

        Db::startTrans();
        try {
            $cardIds = [];
            $cardNos = [];
            $firstCardId = 0;

            for ($i = 0; $i < $quantity; $i++) {
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

                $cardIds[] = $card->id;
                $cardNos[] = $card->getPlainCardNo();

                if ($i == 0) {
                    $firstCardId = $card->id;
                }
            }

            $order->card_id = $firstCardId;
            $order->save();

            $updateResult = ShopProduct::where('id', $product->id)
                ->where('stock', '>=', $quantity)
                ->dec('stock', $quantity)
                ->update();

            if (!$updateResult) {
                Db::rollback();
                return ['success' => false, 'message' => '库存扣减失败'];
            }

            Db::commit();

            if (!empty($order->email)) {
                try {
                    $this->sendCardEmail($order->email, $cardNos, $product->name);
                } catch (\Exception $e) {
                    Log::error('send_card_email_error', ['order_id' => $orderId, 'msg' => $e->getMessage()]);
                }
            }

            return [
                'success' => true,
                'card_id' => $firstCardId,
                'card_ids' => $cardIds,
                'card_nos' => $cardNos,
                'message' => '发卡成功',
            ];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('deliver_card_error', ['order_id' => $orderId, 'msg' => $e->getMessage()]);
            return ['success' => false, 'message' => '发卡失败：' . $e->getMessage()];
        }
    }

    public function sendCardEmail(string $email, $cardNos, string $productName): bool
    {
        if (is_string($cardNos)) {
            $cardNos = [$cardNos];
        }

        $subject = '【卡密发货通知】您购买的' . $productName . '已发货';
        $body = "尊敬的客户：\n\n";
        $body .= "您购买的商品【{$productName}】已支付成功，卡密信息如下：\n\n";

        foreach ($cardNos as $index => $cardNo) {
            $num = $index + 1;
            $body .= "卡密{$num}：{$cardNo}\n";
        }

        $body .= "\n请妥善保管您的卡密信息。\n\n";
        $body .= "感谢您的购买！\n";

        $headers = "From: noreply@example.com\r\n";
        $headers .= "Content-Type: text/plain; charset=utf-8\r\n";

        return @mail($email, $subject, $body, $headers);
    }

    public function getCardInfoByOrder(int $orderId): array
    {
        $order = Order::where('id', $orderId)->with('product')->find();
        if (!$order) {
            return ['success' => false, 'message' => '订单不存在'];
        }

        if (!$order->isPaid()) {
            return ['success' => false, 'message' => '订单未支付'];
        }

        $cards = Card::where('order_id', $order->id)->select();
        if ($cards->isEmpty()) {
            return ['success' => false, 'message' => '卡密信息不存在'];
        }

        $cardNos = [];
        foreach ($cards as $card) {
            $plainCardNo = $card->getPlainCardNo();
            if ($plainCardNo) {
                $cardNos[] = $plainCardNo;
            }
        }

        if (empty($cardNos)) {
            return ['success' => false, 'message' => '卡密信息不存在'];
        }

        return [
            'success' => true,
            'card_no' => $cardNos[0],
            'card_nos' => $cardNos,
            'product_name' => $order->product ? $order->product->name : '',
            'order_no' => $order->order_no,
            'pay_time' => $order->pay_time,
            'quantity' => count($cardNos),
        ];
    }
}
