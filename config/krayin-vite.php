<?php

return [
    'viters' => [

        //        // CRM root
        //        'root' => [
        //            'hot_file'                 => storage_path('framework/vite.hot'),
        //            'build_directory'          => 'build',
        //            'package_assets_directory' => 'resources',
        //        ],

        // ADMIN (Webkul)
        'admin' => [
            // disable HMR volledig
            'hot_file'                 => storage_path('framework/admin-vite.hot'),
            'build_directory'          => 'admin/build',

            // BELANGRIJK — moet exact mappen naar Vite config
            'package_assets_directory' => 'packages/Webkul/Admin/src/Resources/assets',
        ],
    ],
];
