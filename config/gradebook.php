<?php

return [
    'route_prefix' => 'gradebook',
    'api_prefix' => 'v1',
    'view_namespace' => 'illimi-gradebook',
    'menu' => [
        'enabled' => true,
        'label' => 'Gradebook',
        'icon' => 'ri-file-chart-line',
        'route' => 'gradebook.index',
        'permission' => 'view gradebook',
        'children' => [
            [
                'label' => 'Dashboard',
                'icon' => 'ri-dashboard-line',
                'route' => 'gradebook.index',
                'permission' => 'view gradebook dashboard',
            ],
            [
                'label' => 'Assessments',
                'icon' => 'ri-file-list-3-line',
                'route' => 'gradebook.assessments.index',
                'permission' => 'view assessments',
            ],
            [
                'label' => 'Templates',
                'icon' => 'ri-layout-grid-line',
                'route' => 'gradebook.templates.index',
                'permission' => 'view assessment templates',
            ],
            [
                'label' => 'Reports',
                'icon' => 'ri-bar-chart-box-line',
                'route' => 'gradebook.reports.index',
                'permission' => 'view reports',
            ],
            [
                'label' => 'Tokens',
                'icon' => 'ri-key-2-line',
                'route' => 'gradebook.tokens.index',
                'permission' => 'view reports',
            ],
        ],
    ],
];
