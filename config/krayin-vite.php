<?php

return [
    'viters' => [
        'root' => [
            'hot_file'                 => public_path('vite.hot'),
            'build_directory'          => 'build',
            'package_assets_directory' => 'resources',
        ],
        'admin' => [
            'hot_file'                 => public_path('admin-vite.hot'),

            // Laat build outputs op hun eigen plek
            'build_directory'          => 'admin/build',
            //            'package_assets_directory' => 'packages/Webkul/Admin/src/Resources/assets',
            'package_assets_directory' => 'src/Resources/assets',
        ],

        'installer' => [
            'hot_file'                 => public_path('vite.hot'),
            'build_directory'          => 'installer/build',
            'package_assets_directory' => 'src/Resources/assets',
        ],

        'webform' => [
            'hot_file'                 => public_path('vite.hot'),
            'build_directory'          => 'webform/build',
            'package_assets_directory' => 'src/Resources/assets',
        ],
    ],
];
