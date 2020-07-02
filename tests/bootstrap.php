<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

define('ABS_PATH', dirname(__FILE__));

define('MYSQL_CONFIG', [
    'driver' => 'pdo_mysql',
    'prefix' => '',
    'base' => [
        'dbname' => 'aiacrm',
        'port' => 3306,
        'user' => 'root',
        'password' => '123456',
        'charset' => 'utf8mb4',
    ],
    'master' => ['host' => '127.0.0.1'],
    'slaves' => [
        ['host' => '127.0.0.1'],
        ['host' => '127.0.0.1'],
    ],
    'logger' => \Neo\Database\Logger::class,
]);

require dirname(__FILE__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

require_once ABS_PATH . DIRECTORY_SEPARATOR . 'BaseTester.php';
