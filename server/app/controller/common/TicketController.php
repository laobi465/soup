<?php
declare (strict_types = 1);

namespace app\controller\common;

use app\BaseController;
use app\model\Ticket;
use app\model\TicketReply;
use app\service\MessageService;

class TicketController extends BaseController
{
    public function index()
    {
        $page = $this->request->param('page', 1);
        $pageSize = $this->request->param('page_size', 10);
        $status = $this->request->param('status', '');

        $userId = $this->request->userId;

        $query = Ticket::where('user_id', $userId)->order('id', 'desc');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $item) {
            $arr = $item->toArray();
            $arr['status_text'] = $item->status_text;
            $arr['priority_text'] = $item->priority_text;
            $items[] = $arr;
        }

        return success([
            'list' => $items,
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'pageSize' => $list->listRows(),
        ]);
    }

    public function read($id)
    {
        $userId = $this->request->userId;

        $ticket = Ticket::where('id', $id)
            ->where('user_id', $userId)
            ->find();

        if (!$ticket) {
            return error('工单不存在');
        }

        $replies = TicketReply::where('ticket_id', $id)
            ->order('id', 'asc')
            ->select();

        $replyList = [];
        foreach ($replies as $reply) {
            $arr = $reply->toArray();
            $arr['user_type_text'] = $reply->user_type_text;
            $replyList[] = $arr;
        }

        $ticketData = $ticket->toArray();
        $ticketData['status_text'] = $ticket->status_text;
        $ticketData['priority_text'] = $ticket->priority_text;
        $ticketData['replies'] = $replyList;

        return success($ticketData);
    }

    public function save()
    {
        $userId = $this->request->userId;
        $userType = $this->request->userType ?? 2;

        $title = $this->request->param('title', '');
        $content = $this->request->param('content', '');
        $priority = $this->request->param('priority', 2);

        if (empty($title)) {
            return error('工单标题不能为空');
        }
        if (empty($content)) {
            return error('工单内容不能为空');
        }

        $ticketNo = 'TK' . date('YmdHis') . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

        $ticket = new Ticket();
        $ticket->ticket_no = $ticketNo;
        $ticket->user_id = $userId;
        $ticket->user_type = $userType;
        $ticket->title = $title;
        $ticket->content = $content;
        $ticket->priority = $priority;
        $ticket->status = Ticket::STATUS_PENDING;
        $ticket->save();

        return success([
            'id' => $ticket->id,
            'ticket_no' => $ticket->ticket_no,
        ], '工单提交成功');
    }

    public function reply($id)
    {
        $userId = $this->request->userId;
        $userType = $this->request->userType ?? 2;

        $ticket = Ticket::where('id', $id)
            ->where('user_id', $userId)
            ->find();

        if (!$ticket) {
            return error('工单不存在');
        }

        if ($ticket->isClosed()) {
            return error('工单已关闭，无法回复');
        }

        $content = $this->request->param('content', '');
        if (empty($content)) {
            return error('回复内容不能为空');
        }

        $attachments = $this->request->param('attachments', []);

        $reply = new TicketReply();
        $reply->ticket_id = $id;
        $reply->user_id = $userId;
        $reply->user_type = TicketReply::USER_TYPE_USER;
        $reply->content = $content;
        $reply->attachments = !empty($attachments) ? $attachments : null;
        $reply->save();

        if ($ticket->status == Ticket::STATUS_RESOLVED) {
            $ticket->status = Ticket::STATUS_PROCESSING;
            $ticket->save();
        }

        return success(['id' => $reply->id], '回复成功');
    }

    public function close($id)
    {
        $userId = $this->request->userId;

        $ticket = Ticket::where('id', $id)
            ->where('user_id', $userId)
            ->find();

        if (!$ticket) {
            return error('工单不存在');
        }

        if ($ticket->status == Ticket::STATUS_CLOSED) {
            return error('工单已关闭');
        }

        $ticket->status = Ticket::STATUS_CLOSED;
        $ticket->save();

        return success(null, '工单已关闭');
    }
}
