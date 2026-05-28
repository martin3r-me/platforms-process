<?php

return [
    'name' => 'Process',
    'description' => 'Process Module',
    'version' => '1.0.0',
    
    'scope_type' => 'parent',
    
    'routing' => [
        'prefix' => 'process',
        'middleware' => ['web', 'auth'],
    ],
    
    'guard' => 'web',
    
    'navigation' => [
        'main' => [
            'process' => [
                'title' => 'Prozesse',
                'icon' => 'heroicon-o-arrow-path',
                'route' => 'process.processes.index',
            ],
        ],
    ],
    
    'sidebar' => [
        'process' => [
            'title' => 'Prozesse',
            'icon' => 'heroicon-o-arrow-path',
            'items' => [
                'processes' => [
                    'title' => 'Prozesse',
                    'route' => 'process.processes.index',
                    'icon' => 'heroicon-o-arrow-path',
                ],
            ],
        ],
    ],
];
