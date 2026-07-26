<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Announcement;

class AnnouncementController extends BaseController
{
    public function index()
    {
        $page = $this->request->param('page', 1);
        $pageSize = $this->request->param('page_size', 15);
        $type = $this->request->param('type', '');
        $status = $this->request->param('status', '');
        $keyword = $this->request->param('keyword', '');

        $query = Announcement::order('id', 'desc');

        if ($type !== '' && $type !== 'all') {
            $query->where('type', $type);
        }

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($keyword) {
            $query->whereLike('title', '%' . $keyword . '%');
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $item) {
            $arr = $item->toArray();
            $arr['type_text'] = $item->type_text;
            $arr['status_text'] = $item->status_text;
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
        $announcement = Announcement::find($id);
        if (!$announcement) {
            return error('公告不存在');
        }

        $data = $announcement->toArray();
        $data['type_text'] = $announcement->type_text;
        $data['status_text'] = $announcement->status_text;

        return success($data);
    }

    public function save()
    {
        $data = $this->request->post();

        if (empty($data['title'])) {
            return error('公告标题不能为空');
        }

        $announcement = new Announcement();
        $announcement->title = $data['title'];
        $announcement->content = $data['content'] ?? '';
        $announcement->type = $data['type'] ?? 1;
        $announcement->status = $data['status'] ?? 1;
        $announcement->effective_time = !empty($data['effective_time']) ? $data['effective_time'] : null;
        $announcement->expire_time = !empty($data['expire_time']) ? $data['expire_time'] : null;
        $announcement->created_by = $this->request->userId ?? 0;
        $announcement->save();

        return success(['id' => $announcement->id], '公告创建成功');
    }

    public function update($id)
    {
        $announcement = Announcement::find($id);
        if (!$announcement) {
            return error('公告不存在');
        }

        $data = $this->request->post();

        if (isset($data['title'])) {
            $announcement->title = $data['title'];
        }
        if (isset($data['content'])) {
            $announcement->content = $data['content'];
        }
        if (isset($data['type'])) {
            $announcement->type = $data['type'];
        }
        if (isset($data['status'])) {
            $announcement->status = $data['status'];
        }
        if (isset($data['effective_time'])) {
            $announcement->effective_time = !empty($data['effective_time']) ? $data['effective_time'] : null;
        }
        if (isset($data['expire_time'])) {
            $announcement->expire_time = !empty($data['expire_time']) ? $data['expire_time'] : null;
        }

        $announcement->save();

        return success(null, '公告更新成功');
    }

    public function delete($id)
    {
        $announcement = Announcement::find($id);
        if (!$announcement) {
            return error('公告不存在');
        }

        $announcement->delete();

        return success(null, '公告删除成功');
    }

    public function updateStatus($id)
    {
        $announcement = Announcement::find($id);
        if (!$announcement) {
            return error('公告不存在');
        }

        $status = $this->request->param('status', 1);
        $announcement->status = $status;
        $announcement->save();

        return success(null, $status == 1 ? '公告已发布' : '公告已下架');
    }
}
