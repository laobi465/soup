<?php
declare (strict_types = 1);

namespace app\controller\merchant;

use app\BaseController;
use app\service\ApkInjectService;
use think\Request;

class ApkInjectController extends BaseController
{
    /**
     * 创建注入任务（步骤1：获取上传URL）
     * POST /api/merchant/apk-inject/create
     */
    public function create(Request $request)
    {
        $merchantId = $request->merchant_id ?? 0;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $appId = (int) $request->post('app_id', 0);
        $filename = (string) $request->post('filename', '');
        $fileSize = (int) $request->post('file_size', 0);
        $sha256 = (string) $request->post('sha256', '');

        if (empty($appId) || empty($filename) || empty($sha256)) {
            return error('参数不完整', 400);
        }

        if ($fileSize > 104857600) { // 100MB
            return error('文件大小超过限制（100MB）', 400);
        }

        try {
            $service = new ApkInjectService();
            $result = $service->createTask($merchantId, $appId, $filename, $fileSize, $sha256);
            return success($result, '任务创建成功');
        } catch (\RuntimeException $e) {
            return error($e->getMessage(), 400);
        }
    }

    /**
     * 确认上传完成并投递队列（步骤2：上传完成后调用）
     * POST /api/merchant/apk-inject/dispatch
     */
    public function dispatch(Request $request)
    {
        $merchantId = $request->merchant_id ?? 0;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $taskId = (int) $request->post('task_id', 0);

        if (empty($taskId)) {
            return error('参数不完整', 400);
        }

        try {
            $service = new ApkInjectService();
            $service->dispatchTask($taskId, $merchantId);
            return success([], '任务已提交处理');
        } catch (\RuntimeException $e) {
            return error($e->getMessage(), 400);
        }
    }

    /**
     * 任务列表
     * GET /api/merchant/apk-inject/list
     */
    public function list(Request $request)
    {
        $merchantId = $request->merchant_id ?? 0;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $page = (int) $request->get('page', 1);
        $pageSize = (int) $request->get('page_size', 15);

        $service = new ApkInjectService();
        $result = $service->getList($merchantId, $page, $pageSize);
        return success($result);
    }

    /**
     * 任务详情
     * GET /api/merchant/apk-inject/detail/:id
     */
    public function detail(Request $request, $id)
    {
        $merchantId = $request->merchant_id ?? 0;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $service = new ApkInjectService();
        try {
            $result = $service->getDetail((int)$id, $merchantId);
            return success($result);
        } catch (\RuntimeException $e) {
            return error($e->getMessage(), 404);
        }
    }

    /**
     * 获取下载URL
     * GET /api/merchant/apk-inject/download/:id
     */
    public function download(Request $request, $id)
    {
        $merchantId = $request->merchant_id ?? 0;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $service = new ApkInjectService();
        try {
            $url = $service->getDownloadUrl((int)$id, $merchantId);
            return success(['url' => $url]);
        } catch (\RuntimeException $e) {
            return error($e->getMessage(), 400);
        }
    }
}
