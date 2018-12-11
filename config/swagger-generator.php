<?php

return [
    'routes' => [
        'only' => [],
        'matches' => ['/api/*'],
        'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
    ],
    'output' => [
        'path' => 'public/swagger',
        'file_name' => 'main.yml',
    ],
    'content' => [
        'openapi' => '3.0.0',
        'info' => [
            'description' => 'Service documentation',
            'version' => '1.0.0',
            'title' => 'Laravel API',
        ],
        'servers' => [
            [
                'url' => 'https://localhost',
                'description' => 'Local server',
            ],
        ],
        'components' => [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
            ],
        ],
    ],
];
