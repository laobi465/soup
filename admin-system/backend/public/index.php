<?php
// +----------------------------------------------------------------------
// | admin-system 应用入口
// +----------------------------------------------------------------------
// | 所有 HTTP 请求由 web server (nginx/apache) 转发至此
// +----------------------------------------------------------------------

namespace think;

// 定义项目根目录
define('DOC_ROOT', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR);

// 加载 Composer 自动加载
require DOC_ROOT . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

// 加载 .env 配置文件（存在时加载）
$envPath = DOC_ROOT . '.env';
if (is_file($envPath)) {
    (new \think\Env())->load($envPath);
}

// 实例化应用并初始化
$app = new App();
$app->initialize();

// 执行 HTTP 应用并输出响应
$http = $app->http;
$response = $http->run();
$response->send();

// 结束应用（执行中间件后置逻辑与日志写入）
$http->end($response);
