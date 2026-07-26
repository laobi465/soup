<?php

use think\Response;

if (!function_exists('success')) {
    function success($data = null, string $message = 'success', int $code = 0): Response
    {
        return json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ]);
    }
}

if (!function_exists('error')) {
    function error(string $message = 'error', int $code = 1, $data = null): Response
    {
        return json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ]);
    }
}

if (!function_exists('unauthorized')) {
    function unauthorized(string $message = '未登录或登录已过期'): Response
    {
        return json([
            'code'    => 401,
            'message' => $message,
            'data'    => null,
        ])->code(401);
    }
}

if (!function_exists('forbidden')) {
    function forbidden(string $message = '无权限访问'): Response
    {
        return json([
            'code'    => 403,
            'message' => $message,
            'data'    => null,
        ])->code(403);
    }
}
