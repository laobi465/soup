<?php

return [
    'secret'         => env('jwt.secret', ''),
    'expire'         => (int) env('jwt.expire', 7200),
    'refresh_expire' => (int) env('jwt.refresh_expire', 604800),
    'algorithm'      => 'HS256',
    'header_key'     => 'Authorization',
    'prefix'         => 'Bearer ',
    'blacklist_prefix' => 'jwt_blacklist:',
];
