<?php
declare (strict_types = 1);

namespace app\controller\common;

use app\BaseController;
use app\model\Announcement;

class AnnouncementController extends BaseController
{
    public function index()
    {
        $page = $this->request->param('page', 1);
        $pageSize = $this->request->param('page_size', 10);
        $type = $this->request->param('type', '');

        $query = Announcement::where('status', 1)
            ->effective()
            ->order('id', 'desc');

        if ($type && $type !== 'all') {
            $query->where('type', $type);
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $item) {
            $arr = $item->toArray();
            $arr['type_text'] = $item->type_text;
            unset($arr['content']);
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
        $announcement = Announcement::where('id', $id)
            ->where('status', 1)
            ->effective()
            ->find();

        if (!$announcement) {
            return error('公告不存在或已失效');
        }

        $data = $announcement->toArray();
        $data['type_text'] = $announcement->type_text;

        return success($data);
    }

    public function popup()
    {
        $lastViewedId = (int)$this->request->param('last_id', 0);

        $announcement = Announcement::where('status', 1)
            ->effective()
            ->where('id', '>', $lastViewedId)
            ->order('id', 'desc')
            ->find();

        if (!$announcement) {
            return success([
                'has_new' => false,
                'announcement' => null,
            ]);
        }

        $data = $announcement->toArray();
        $data['type_text'] = $announcement->type_text;

        return success([
            'has_new' => true,
            'announcement' => $data,
        ]);
    }
}
