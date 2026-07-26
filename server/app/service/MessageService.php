<?php
declare (strict_types = 1);

namespace app\service;

use app\model\Message;

class MessageService
{
    public static function sendSystemMessage(
        int $userId,
        string $title,
        string $content = '',
        int $type = Message::TYPE_SYSTEM,
        array $extra = [],
        int $senderId = 0
    ): Message {
        $message = new Message();
        $message->user_id = $userId;
        $message->title = $title;
        $message->content = $content;
        $message->type = $type;
        $message->is_read = 0;
        $message->sender_id = $senderId;
        $message->extra = !empty($extra) ? $extra : null;
        $message->save();

        return $message;
    }

    public static function sendBatchMessage(
        array $userIds,
        string $title,
        string $content = '',
        int $type = Message::TYPE_SYSTEM,
        array $extra = []
    ): int {
        if (empty($userIds)) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $data = [];

        foreach ($userIds as $userId) {
            $data[] = [
                'user_id' => $userId,
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'is_read' => 0,
                'sender_id' => 0,
                'extra' => !empty($extra) ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return \think\facade\Db::name('messages')->insertAll($data);
    }

    public static function getUnreadCount(int $userId): int
    {
        return Message::where('user_id', $userId)
            ->where('is_read', 0)
            ->count();
    }

    public static function markAsRead(int $messageId, int $userId): bool
    {
        $message = Message::where('id', $messageId)
            ->where('user_id', $userId)
            ->find();

        if (!$message) {
            return false;
        }

        $message->is_read = 1;
        $message->read_at = date('Y-m-d H:i:s');
        $message->save();

        return true;
    }

    public static function markAllAsRead(int $userId): int
    {
        $now = date('Y-m-d H:i:s');

        return Message::where('user_id', $userId)
            ->where('is_read', 0)
            ->update([
                'is_read' => 1,
                'read_at' => $now,
                'updated_at' => $now,
            ]);
    }

    public static function getMessageList(
        int $userId,
        int $page = 1,
        int $pageSize = 10,
        array $filters = []
    ): array {
        $query = Message::where('user_id', $userId)->order('id', 'desc');

        if (!empty($filters['type'])) {
            $query->where('type', intval($filters['type']));
        }

        if (isset($filters['is_read']) && $filters['is_read'] !== '') {
            $query->where('is_read', intval($filters['is_read']));
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $message) {
            $item = $message->toArray();
            $item['type_text'] = $message->type_text;
            $items[] = $item;
        }

        $unreadCount = self::getUnreadCount($userId);

        return [
            'list' => $items,
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'pageSize' => $list->listRows(),
            'unread_count' => $unreadCount,
        ];
    }

    public static function sendPackageExpireReminder(int $userId, string $packageName, string $expireTime): Message
    {
        $title = '套餐即将到期提醒';
        $content = "您的套餐「{$packageName}」将于 {$expireTime} 到期，请及时续费以免影响使用。";

        return self::sendSystemMessage($userId, $title, $content, Message::TYPE_PACKAGE, [
            'package_name' => $packageName,
            'expire_time' => $expireTime,
        ]);
    }

    public static function sendCardExpireReminder(int $userId, int $cardId, string $expireTime): Message
    {
        $title = '卡密即将到期提醒';
        $content = "您的卡密（ID：{$cardId}）将于 {$expireTime} 到期。";

        return self::sendSystemMessage($userId, $title, $content, Message::TYPE_CARD, [
            'card_id' => $cardId,
            'expire_time' => $expireTime,
        ]);
    }

    public static function sendWithdrawNotice(int $userId, string $amount, int $status): Message
    {
        $statusText = $status == 1 ? '已到账' : '已拒绝';
        $title = '提现已' . $statusText;
        $content = "您的提现申请金额 ¥{$amount} {$statusText}。";

        return self::sendSystemMessage($userId, $title, $content, Message::TYPE_WITHDRAW, [
            'amount' => $amount,
            'status' => $status,
        ]);
    }

    public static function sendTicketStatusNotice(int $userId, int $ticketId, string $status): Message
    {
        $title = '工单状态变更通知';
        $content = "您的工单（ID：{$ticketId}）状态已变更为：{$status}。";

        return self::sendSystemMessage($userId, $title, $content, Message::TYPE_TICKET, [
            'ticket_id' => $ticketId,
            'status' => $status,
        ]);
    }

    public static function sendAnnouncement(string $title, string $content, array $userIds = []): int
    {
        if (empty($userIds)) {
            return 0;
        }

        return self::sendBatchMessage($userIds, $title, $content, Message::TYPE_SYSTEM);
    }

    public static function sendAlert(int $userId, string $title, string $content, array $extra = []): Message
    {
        return self::sendSystemMessage($userId, $title, $content, Message::TYPE_ALERT, $extra);
    }
}
