<?php
// +----------------------------------------------------------------------
// | 视图配置 - 通过 env() 读取，零硬编码
// +----------------------------------------------------------------------

return [
    // 模板引擎类型
    'type'          => 'Think',
    // 模板路径
    'view_path'     => env('VIEW_PATH', ''),
    // 模板后缀
    'view_suffix'   => env('VIEW_SUFFIX', 'html'),
    // 模板文件名分隔符
    'view_depr'     => DIRECTORY_SEPARATOR,
    // 模板引擎普通标签开始标记
    'tpl_begin'     => env('TPL_BEGIN', '{'),
    // 模板引擎普通标签结束标记
    'tpl_end'       => env('TPL_END', '}'),
    // 标签库标签开始标记
    'taglib_begin'   => env('TAGLIB_BEGIN', '{'),
    // 标签库标签结束标记
    'taglib_end'     => env('TAGLIB_END', '}'),
    // 模板自动渲染
    'auto_rule'     => 1,
    // 模板根目录
    'view_root'     => '',
    // 模板替换规则
    'tpl_replace_string' => [],
    // 模板变量
    'tpl_var'       => [],
];
