<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class TicketReply extends Model
{
    protected $name = 'ticket_replies';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $json = ['attachments'];
    protected $jsonAssoc = true;

    const USER_TYPE_USER = 1;
    const USER_TYPE_ADMIN = 2;

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getUserTypeTextAttr($value, $data)
    {
        return $data['user_type'] == 1 ? '用户' : '管理员';
    }

    public function scopeTicket($query, $ticketId)
    {
        return $query->where('ticket_id', $ticketId);
    }
}
