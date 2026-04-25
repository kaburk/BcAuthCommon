<?php

return [
    'permission' => [
        'BcAuthLoginLogsAdmin' => [
            'title' => __d('baser_core', '認証ログイン履歴'),
            'plugin' => 'BcAuthCommon',
            'type' => 'Admin',
            'items' => [
                'Index' => [
                    'title' => __d('baser_core', '一覧'),
                    'url' => '/baser/admin/bc-auth-common/bc_auth_login_logs/index',
                    'method' => 'GET',
                    'auth' => false,
                ],
                'View' => [
                    'title' => __d('baser_core', '詳細'),
                    'url' => '/baser/admin/bc-auth-common/bc_auth_login_logs/view/*',
                    'method' => 'GET',
                    'auth' => false,
                ],
                'Delete' => [
                    'title' => __d('baser_core', '削除'),
                    'url' => '/baser/admin/bc-auth-common/bc_auth_login_logs/delete/*',
                    'method' => 'POST',
                    'auth' => false,
                ],
                'Batch' => [
                    'title' => __d('baser_core', '一括削除'),
                    'url' => '/baser/admin/bc-auth-common/bc_auth_login_logs/batch',
                    'method' => 'POST',
                    'auth' => false,
                ],
            ],
        ],
    ],
];
