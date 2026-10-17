<?php

return [
    'use' => 'default',
    'path' => 'horizon',
    'middleware' => ['web', 'auth:api', 'conta-pronta', 'perfil:administrador', 'scope:admin:manage', 'pagina-admin:manutencao'],
    'waits' => ['redis:fiscal' => 60],
    'fast_termination' => false,
    'memory_limit' => 128,
    'defaults' => [
        'fiscal' => [
            'connection' => 'redis', 'queue' => ['fiscal'], 'balance' => 'auto',
            'autoScalingStrategy' => 'time', 'minProcesses' => 1, 'maxProcesses' => 2,
            'maxTime' => 3600, 'maxJobs' => 500, 'memory' => 256, 'tries' => 40,
            'timeout' => 210, 'nice' => 0,
        ],
        'default' => [
            'connection' => 'redis', 'queue' => ['default'], 'balance' => 'simple',
            'maxProcesses' => 1, 'maxTime' => 3600, 'maxJobs' => 500,
            'memory' => 128, 'tries' => 3, 'timeout' => 60, 'nice' => 0,
        ],
    ],
    'environments' => ['*' => ['fiscal' => [], 'default' => []]],
];
