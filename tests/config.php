<?php

/*
 * Redis
 */
$NEO_CONFIG['redis'] = [
    'neo' => [
        'master' => [
            'host' => '127.0.0.1',
            'port' => 6379,
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
            'dbname' => 'crm',
            'port' => 3306,
            'user' => 'root',
            'password' => 'liyuntian',
            'charset' => 'utf8mb4',
        ],
        'primary' => ['host' => '127.0.0.1'],
        'replica' => [
            ['host' => 'localhost'],
            ['host' => '127.0.0.1'],
        ],
        'logger' => \Neo\Database\Logger::class,
    ],
    'sqlite' => [
        'driver' => 'pdo_sqlite',
        'prefix' => '',
        'url' => 'sqlite:///' . ABS_PATH . '/sqlite_test.db',
        'logger' => \Neo\Database\Logger::class,
    ],
];

define('MYSQL_CONFIG', $NEO_CONFIG['database']['mysql']);
define('SQLITE_CONFIG', $NEO_CONFIG['database']['sqlite']);

/*
 * 文件日志级别
 */
// 日志级别: level:
// DEBUG = 100;
// INFO = 200;
// NOTICE = 250;
// WARNING = 300;
// ERROR = 400;
// CRITICAL = 500;
// ALERT = 550;
// EMERGENCY = 600;
// 日志种类: types: file, redis
// NeoLog::info($type, $msg, $context)
$NEO_CONFIG['logger'] = [
    'level' => 200,
    'dir' => ABS_PATH . DIRECTORY_SEPARATOR . 'logs',
    'id' => sha1(uniqid('', true) . str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 16))),
    'types' => ['file'],
    'file' => [
        'pertype' => true, // 是否每个$type一个日志文件
        'typename' => 'neo', // 如果pertype==false，可以指定日志文件名称，默认为neo
        'formatter' => 'json', // 文件内容格式，默认为json，可选：line, json
    ],
];

/*
 * 时区，和基于时区的时间偏移
 *
 * @link https://www.php.net/manual/en/timezones.php
 */
$NEO_CONFIG['datetime'] = [
    'zone' => 'Asia/Shanghai',
    'offset' => 28800,
];
