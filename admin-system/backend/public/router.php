<?php
// +----------------------------------------------------------------------
// | admin-system 内置 server 路由
// +----------------------------------------------------------------------
// | 用于 php -S localhost:8000 -t public public/router.php
// +----------------------------------------------------------------------

namespace think;

// 项目根目录
define('DOC_ROOT', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR);

// 加载 Composer 自动加载
require DOC_ROOT . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

// 加载 .env 配置
$envPath = DOC_ROOT . '.env';
if (is_file($envPath)) {
    (new \think\Env())->load($envPath);
}

// 静态资源直接返回（如上传文件等）
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$staticFile = __DIR__ . $uri;
if ($uri !== '/' && is_file($staticFile)) {
    return false;
}

// 启动应用并处理请求
$app = new App();
$app->initialize();
$http = $app->http;
$response = $http->run();
$response->send();
$http->end($response);
