<?php

declare(strict_types=1);

$config = [
    'BcApp' => [
        'adminNavigation' => [
            'Plugins' => [
                'menus' => [
                    'BcAuthLoginLogs' => [
                        'title' => __d('baser_core', 'ログイン履歴'),
                        'url' => [
                            'prefix' => 'Admin',
                            'plugin' => 'BcAuthCommon',
                            'controller' => 'BcAuthLoginLogs',
                            'action' => 'index',
                        ],
                    ],
                ],
            ],
        ],
    ],
];

return $config;
