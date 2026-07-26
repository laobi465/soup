<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Ticket;
use app\model\TicketReply;
use app\service\MessageService;

class TicketController extends BaseController
{
    public function index()
    {
        $page = $this->request->param('page', 1);
        $pageSize = $this->request->param('page_size', 15);
        $status = $this->request->param('status', '');
        $priority = $this->request->param('priority', '');
        $keyword = $this->request->param('keyword', '');
        $handlerId = $this->request->param('handler_id', '');

        $query = Ticket::order('id', 'desc');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($priority && $priority !== 'all') {
            $query->where('priority', $priority);
        }

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('title', '%' . $keyword . '%')
                    ->whereOr('ticket_no', 'like', '%' . $keyword . '%');
            });
        }

        if ($handlerId !== '' && $handlerId !== 'all') {
            if ($handlerId == 0) {
                $query->where('handler_id', 0);
            } else {
                $query->where('handler_id', $handlerId);
            }
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
            $arr['user_type_text'] = $item->user_type_text;
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
        $ticket = Ticket::with(['replies'])
            ->where('id', $id)
            ->find();

        if (!$ticket) {
            return error('工单不存在');
        }

        $ticketData = $ticket->toArray();
        $ticketData['status_text'] = $ticket->status_text;
        $ticketData['priority_text'] = $ticket->priority_text;
        $ticketData['user_type_text'] = $ticket->user_type_text;

        if (isset($ticketData['replies'])) {
            foreach ($ticketData['replies'] as &$reply) {
                $reply['user_type_text'] = $reply['user_type'] == 1 ? '用户' : '管理员';
            }
        }

        return success($ticketData);
    }

    public function reply($id)
    {
        $adminId = $this->request->userId ?? 0;

        $ticket = Ticket::find($id);
        if (!$ticket) {
            return error('工单不存在');
        }

        if ($ticket->status == Ticket::STATUS_CLOSED) {
            return error('工单已关闭，无法回复');
        }

        $content = $this->request->param('content', '');
        if (empty($content)) {
            return error('回复内容不能为空');
        }

        $attachments = $this->request->param('attachments', []);

        $reply = new TicketReply();
        $reply->ticket_id = $id;
        $reply->user_id = $adminId;
        $reply->user_type = TicketReply::USER_TYPE_ADMIN;
        $reply->content = $content;
        $reply->attachments = !empty($attachments) ? $attachments : null;
        $reply->save();

        $oldStatus = $ticket->status;
        if ($ticket->status == Ticket::STATUS_PENDING) {
            $ticket->status = Ticket::STATUS_PROCESSING;
            if ($ticket->handler_id == 0) {
                $ticket->handler_id = $adminId;
            }
        }
        $ticket->save();

        if ($ticket->status != $oldStatus) {
            MessageService::sendTicketStatusNotice(
                $ticket->user_id,
                $ticket->id,
                $ticket->status_text
            );
        }

        return success(['id' => $reply->id], '回复成功');
    }

    public function updateStatus($id)
    {
        $ticket = Ticket::find($id);
        if (!$ticket) {
            return error('工单不存在');
        }

        $status = $this->request->param('status', 0);
        if (!in_array($status, [1, 2, 3, 4])) {
            return error('无效的状态');
        }

        $oldStatus = $ticket->status;
        $ticket->status = $status;
        $ticket->save();

        if ($oldStatus != $status) {
            $statusText = [
                1 => '待处理',
                2 => '处理中',
                3 => '已解决',
                4 => '已关闭',
            ];
            MessageService::sendTicketStatusNotice(
                $ticket->user_id,
                $ticket->id,
                $statusText[$status]
            );
        }

        return success(null, '状态更新成功');
    }

    public function assign($id)
    {
        $ticket = Ticket::find($id);
        if (!$ticket) {
            return error('工单不存在');
        }

        $handlerId = $this->request->param('handler_id', 0);
        $ticket->handler_id = $handlerId;

        if ($ticket->status == Ticket::STATUS_PENDING && $handlerId > 0) {
            $ticket->status = Ticket::STATUS_PROCESSING;
        }

        $ticket->save();

        return success(null, '分配成功');
    }

    public function stats()
    {
        $pending = Ticket::where('status', Ticket::STATUS_PENDING)->count();
        $processing = Ticket::where('status', Ticket::STATUS_PROCESSING)->count();
        $resolved = Ticket::where('status', Ticket::STATUS_RESOLVED)->count();
        $closed = Ticket::where('status', Ticket::STATUS_CLOSED)->count();

        return success([
            'pending' => $pending,
            'processing' => $processing,
            'resolved' => $resolved,
            'closed' => $closed,
            'total' => $pending + $processing + $resolved + $closed,
        ]);
    }
}
