<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\service\SystemConfigService;

class SystemConfigController extends BaseController
{
    public function index()
    {
        $group = $this->request->param('group', '');
        $groups = SystemConfigService::getGroupList();

        if (!empty($group)) {
            $data = $groups[$group] ?? [];
            return success([
                'group' => $group,
                'configs' => $data,
            ]);
        }

        return success([
            'groups' => $groups,
        ]);
    }

    public function getByGroup()
    {
        $group = $this->request->param('group', 'basic');
        $configs = SystemConfigService::getByGroup($group);

        return success([
            'group' => $group,
            'configs' => $configs,
        ]);
    }

    public function save()
    {
        $data = $this->request->post();
        if (empty($data)) {
            return error('配置数据不能为空');
        }

        $result = SystemConfigService::saveBatch($data);

        if ($result) {
            return success(null, '配置保存成功');
        }

        return error('配置保存失败');
    }

    public function clearCache()
    {
        SystemConfigService::clearCache();
        return success(null, '缓存清除成功');
    }
}
