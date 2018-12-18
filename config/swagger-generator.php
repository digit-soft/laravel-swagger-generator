<?php

return [
    /*
    |---------------------------------------------------------
    | Routes filters
    |---------------------------------------------------------
    */
    'routes' => [
        'only' => [],
        'matches' => ['/api/*', '/attachments/*', '/images/*'],
        'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
    ],
    /*
    |---------------------------------------------------------
    | Settings for output files
    |---------------------------------------------------------
    | For absolute URL start it with slash "/", otherwise relative to app base path
    */
    'output' => [
        'path' => 'public/swagger',
        'file_name' => 'main.yml',
    ],
    /*
    |---------------------------------------------------------
    | Content array to merge with
    |---------------------------------------------------------
    */
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
    /*
    |---------------------------------------------------------
    | Strip base url prefix for output, NULL to disable
    |---------------------------------------------------------
    */
    'stripBaseUrl' => null,
];
