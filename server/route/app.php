<?php
use think\facade\Route;

Route::group('api/admin', function () {
    Route::group('auth', function () {
        Route::post('login', 'admin.AuthController/login')
            ->middleware([\app\middleware\LoginThrottleMiddleware::class]);
        Route::post('refresh', 'admin.AuthController/refresh');
        Route::post('logout', 'admin.AuthController/logout');
        Route::get('info', 'admin.AuthController/info');
    });

    Route::group('packages', function () {
        Route::get('', 'admin.PackageController/index');
        Route::get(':id', 'admin.PackageController/read');
        Route::post('', 'admin.PackageController/save');
        Route::put(':id', 'admin.PackageController/update');
        Route::delete(':id', 'admin.PackageController/delete');
    })->middleware([\app\middleware\PermissionMiddleware::class . ':package:manage']);

    Route::group('apps', function () {
        Route::get('', 'admin.AppController/index');
        Route::get(':id', 'admin.AppController/read');
        Route::put(':id/status', 'admin.AppController/updateStatus');
        Route::delete(':id', 'admin.AppController/delete');
    })->middleware([\app\middleware\PermissionMiddleware::class . ':app:manage']);

    Route::group('merchants', function () {
        Route::get('', 'admin.MerchantController/index');
        Route::get(':id', 'admin.MerchantController/read');
        Route::put(':id/status', 'admin.MerchantController/updateStatus');
        Route::put(':id/reset-password', 'admin.MerchantController/resetPassword');
        Route::put(':id/adjust-quota', 'admin.MerchantController/adjustQuota');
        Route::put(':id/change-package', 'admin.MerchantController/changePackage');
    })->middleware([\app\middleware\PermissionMiddleware::class . ':merchant:manage']);

    Route::group('cards', function () {
        Route::get('', 'admin.CardController/index');
        Route::get(':id', 'admin.CardController/read');
        Route::put(':id/ban', 'admin.CardController/ban');
        Route::put(':id/unban', 'admin.CardController/unban');
    })->middleware([\app\middleware\PermissionMiddleware::class . ':card:manage']);

    Route::group('payment', function () {
        Route::get('config', 'admin.PaymentController/getConfig');
        Route::put('config', 'admin.PaymentController/updateConfig');
    })->middleware([\app\middleware\PermissionMiddleware::class . ':payment:manage']);

    Route::group('orders', function () {
        Route::get('', 'admin.PaymentController/orders');
        Route::get(':id', 'admin.PaymentController/orderDetail');
        Route::post('refund', 'admin.PaymentController/refund');
    })->middleware([\app\middleware\PermissionMiddleware::class . ':order:manage']);

    Route::group('dashboard', function () {
        Route::get('', 'admin.DashboardController/index');
    });

    Route::group('stats', function () {
        Route::get('overview', 'admin.DashboardController/overview');
        Route::get('trend', 'admin.DashboardController/trend');
    });

    Route::group('risk', function () {
        Route::get('blacklist', 'admin.RiskController/blacklistIndex');
        Route::post('blacklist', 'admin.RiskController/blacklistSave');
        Route::put('blacklist/:id', 'admin.RiskController/blacklistUpdate');
        Route::delete('blacklist/:id', 'admin.RiskController/blacklistDelete');
        Route::get('alerts', 'admin.RiskController/alerts');
        Route::get('overview', 'admin.RiskController/overview');
    })->middleware([\app\middleware\PermissionMiddleware::class . ':risk:manage']);

    Route::group('logs', function () {
        Route::get('operation', 'admin.LogController/operation');
        Route::get('login', 'admin.LogController/login');
        Route::get('api', 'admin.LogController/api');
    })->middleware([\app\middleware\PermissionMiddleware::class . ':system:log']);

    Route::group('config', function () {
        Route::get('', 'admin.SystemConfigController/index');
        Route::get('group', 'admin.SystemConfigController/getByGroup');
        Route::put('', 'admin.SystemConfigController/save');
        Route::post('clear-cache', 'admin.SystemConfigController/clearCache');
    })->middleware([\app\middleware\PermissionMiddleware::class . ':system:config']);

    Route::group('announcements', function () {
        Route::get('', 'admin.AnnouncementController/index');
        Route::get(':id', 'admin.AnnouncementController/read');
        Route::post('', 'admin.AnnouncementController/save');
        Route::put(':id', 'admin.AnnouncementController/update');
        Route::delete(':id', 'admin.AnnouncementController/delete');
        Route::put(':id/status', 'admin.AnnouncementController/updateStatus');
    })->middleware([\app\middleware\PermissionMiddleware::class . ':content:announcement']);

    Route::group('tickets', function () {
        Route::get('', 'admin.TicketController/index');
        Route::get('stats', 'admin.TicketController/stats');
        Route::get(':id', 'admin.TicketController/read');
        Route::post(':id/reply', 'admin.TicketController/reply');
        Route::put(':id/status', 'admin.TicketController/updateStatus');
        Route::put(':id/assign', 'admin.TicketController/assign');
    })->middleware([\app\middleware\PermissionMiddleware::class . ':ticket:manage']);
})->middleware([
    \app\middleware\AuthMiddleware::class,
]);

Route::group('api/merchant', function () {
    Route::group('profile', function () {
        Route::get('', 'merchant.ProfileController/index');
        Route::put('', 'merchant.ProfileController/update');
    });

    Route::put('change-password', 'merchant.ProfileController/changePassword');

    Route::group('package', function () {
        Route::get('current', 'merchant.PackageController/current');
        Route::post('upgrade', 'merchant.PackageController/upgrade');
    });

    Route::get('packages', 'merchant.PackageController/index');

    Route::group('apps', function () {
        Route::get('', 'merchant.AppController/index');
        Route::get(':id', 'merchant.AppController/read');
        Route::post('', 'merchant.AppController/save')
            ->middleware([\app\middleware\QuotaMiddleware::class . ':app']);
        Route::put(':id', 'merchant.AppController/update');
        Route::put(':id/status', 'merchant.AppController/updateStatus');
        Route::post(':id/reset-secret', 'merchant.AppController/resetSecret');
    });

    Route::group('wallet', function () {
        Route::get('', 'merchant.WalletController/index');
        Route::get('transactions', 'merchant.WalletController/transactions');
        Route::post('recharge', 'merchant.WalletController/recharge');
    });

    Route::group('cards', function () {
        Route::get('', 'merchant.CardController/index');
        Route::get(':id', 'merchant.CardController/read');
        Route::post('generate', 'merchant.CardController/generate');
        Route::post('batch-generate', 'merchant.CardController/batchGenerate');
        Route::post('export', 'merchant.CardController/export');
        Route::post('import', 'merchant.CardController/import');
        Route::put(':id/ban', 'merchant.CardController/ban');
        Route::put(':id/unban', 'merchant.CardController/unban');
        Route::put(':id/void', 'merchant.CardController/void');
        Route::put(':id/renew', 'merchant.CardController/renew');
        Route::delete(':id/device/:deviceId', 'merchant.CardController/unbindDevice');
    });

    Route::group('orders', function () {
        Route::get('', 'merchant.OrderController/index');
        Route::get(':id', 'merchant.OrderController/read');
        Route::post('recharge', 'merchant.OrderController/recharge');
        Route::post('package', 'merchant.OrderController/package');
        Route::post('refund', 'merchant.OrderController/refund');
    });

    Route::group('shop/products', function () {
        Route::get('', 'merchant.ShopProductController/index');
        Route::get(':id', 'merchant.ShopProductController/read');
        Route::post('', 'merchant.ShopProductController/save');
        Route::put(':id', 'merchant.ShopProductController/update');
        Route::put(':id/status', 'merchant.ShopProductController/updateStatus');
        Route::delete(':id', 'merchant.ShopProductController/delete');
    });

    Route::group('agents', function () {
        Route::get('', 'merchant.AgentController/index');
        Route::get('tree', 'merchant.AgentController/tree');
        Route::get('commission', 'merchant.AgentController/commission');
        Route::get(':id', 'merchant.AgentController/read');
        Route::get(':id/orders', 'merchant.AgentController/orders');
        Route::put(':id/level', 'merchant.AgentController/updateLevel');
        Route::put(':id/status', 'merchant.AgentController/updateStatus');
    });

    Route::group('dashboard', function () {
        Route::get('', 'merchant.DashboardController/index');
    });

    Route::group('stats', function () {
        Route::get('card', 'merchant.DashboardController/cardStats');
        Route::get('api', 'merchant.DashboardController/apiStats');
    });

    Route::group('sub-accounts', function () {
        Route::get('', 'merchant.SubAccountController/index');
        Route::post('', 'merchant.SubAccountController/save');
        Route::put(':id', 'merchant.SubAccountController/update');
        Route::put(':id/status', 'merchant.SubAccountController/updateStatus');
        Route::put(':id/reset-password', 'merchant.SubAccountController/resetPassword');
        Route::delete(':id', 'merchant.SubAccountController/delete');
    });

    Route::group('sub-roles', function () {
        Route::get('', 'merchant.SubRoleController/index');
        Route::post('', 'merchant.SubRoleController/save');
        Route::put(':id', 'merchant.SubRoleController/update');
        Route::delete(':id', 'merchant.SubRoleController/delete');
    });
})->middleware([
    \app\middleware\AuthMiddleware::class,
    \app\middleware\MerchantStatusMiddleware::class,
    \app\middleware\PermissionMiddleware::class,
    \app\middleware\DataPermissionMiddleware::class,
]);

Route::group('api', function () {
    Route::group('register', function () {
        Route::post('merchant', 'api.RegisterController/merchant')
            ->middleware([\app\middleware\LoginThrottleMiddleware::class]);
    });

    Route::group('pay/notify', function () {
        Route::post('caihong', 'api.PayNotifyController/caihong');
    });

    Route::group('shop', function () {
        Route::get('order/query', 'shop.ShopController/queryOrder');
        Route::get(':merchantNo', 'shop.ShopController/index');
        Route::get(':merchantNo/products', 'shop.ShopController/products');
        Route::get(':merchantNo/products/:id', 'shop.ShopController/productDetail');
        Route::post(':merchantNo/order/create', 'shop.ShopController/createOrder');
    });
});

Route::group('api/agent', function () {
    Route::get('dashboard', 'agent.DashboardController/index');
    Route::get('invite', 'agent.AgentController/invite');
    Route::get('team', 'agent.AgentController/team');
    Route::get('commission', 'agent.AgentController/commission');
    Route::get('wallet', 'agent.AgentController/wallet');
    Route::post('withdraw', 'agent.AgentController/withdraw');
    Route::get('withdraw/list', 'agent.AgentController/withdrawList');
})->middleware([
    \app\middleware\AuthMiddleware::class,
]);

Route::group('api/messages', function () {
    Route::get('', 'common.MessageController/index');
    Route::get('unread-count', 'common.MessageController/unreadCount');
    Route::put(':id/read', 'common.MessageController/markAsRead');
    Route::put('read-all', 'common.MessageController/markAllAsRead');
})->middleware([
    \app\middleware\AuthMiddleware::class,
]);

Route::group('api/upload', function () {
    Route::post('image', 'common.UploadController/uploadImage');
    Route::post('file', 'common.UploadController/uploadFile');
    Route::get('config', 'common.UploadController/getUploadConfig');
})->middleware([
    \app\middleware\AuthMiddleware::class,
]);

Route::group('api/announcements', function () {
    Route::get('', 'common.AnnouncementController/index');
    Route::get('popup', 'common.AnnouncementController/popup');
    Route::get(':id', 'common.AnnouncementController/read');
});

Route::group('api/tickets', function () {
    Route::get('', 'common.TicketController/index');
    Route::post('', 'common.TicketController/save');
    Route::get(':id', 'common.TicketController/read');
    Route::post(':id/reply', 'common.TicketController/reply');
    Route::put(':id/close', 'common.TicketController/close');
})->middleware([
    \app\middleware\AuthMiddleware::class,
]);

Route::group('api/v1', function () {
    Route::post('card/verify', 'api.CardApiController/verify');
    Route::post('card/activate', 'api.CardApiController/activate');
    Route::post('card/rebind', 'api.CardApiController/rebind');
    Route::post('card/query', 'api.CardApiController/query');
    Route::post('card/heartbeat', 'api.CardApiController/heartbeat');
    Route::get('card/online-count', 'api.CardApiController/onlineCount');
    Route::get('app/announcement', 'api.CardApiController/announcement');
    Route::post('user/register', 'api.CardApiController/register');
    Route::post('user/login', 'api.CardApiController/login');
})->middleware([
    \app\middleware\ApiAuthMiddleware::class,
    \app\middleware\ApiRateLimitMiddleware::class,
]);
