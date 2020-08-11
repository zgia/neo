<?php

/*
 * Redis
 */
$NEO_CONFIG['redis'] = [
    'neo' => [
        'master' => [
            'host' => '127.0.0.1',
            'port' => 6271,
            'password' => 'thisisapassword',
            'options' => [
                \Redis::OPT_PREFIX => 'neo:',
            ],
        ],
    ],
];

/*
 * MySQL
 */
$NEO_CONFIG['database'] = [
    'mysql' => [
        'driver' => 'pdo_mysql',
        'prefix' => '',
        'base' => [
            'dbname' => 'dbname',
            'port' => 3306,
            'user' => 'dbuser',
            'password' => 'thisisapassword',
            'charset' => 'utf8mb4',
        ],
        'master' => ['host' => '127.0.0.1'],
        'slaves' => [
            ['host' => '127.0.0.1'],
            ['host' => '127.0.0.1'],
        ],
        'logger' => \Neo\Database\Logger::class,
    ],
];

define('MYSQL_CONFIG', $NEO_CONFIG['database']['mysql']);
