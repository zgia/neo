<?php

/**
 * 单文件测试
 * 
 * phpunit --configuration ./tests/phpunit.xml ./tests/Neo/RequestTest.php
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

define('ABS_PATH', dirname(__FILE__));

require dirname(__FILE__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

require_once ABS_PATH . DIRECTORY_SEPARATOR . 'config.php';

require_once ABS_PATH . DIRECTORY_SEPARATOR . 'BaseTester.php';

// 初始化NeoFrame
Neo\Config::load($NEO_CONFIG);
$neo = new Neo\Neo();