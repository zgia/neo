<?php

use PHPUnit\Framework\TestCase;

/**
 * 测试基类
 *
 * Class BaseTester
 *
 * @internal
 * @coversNothing
 */
class BaseTester extends TestCase
{
    /**
     * @var \Neo\Database\MySQL
     */
    public $db;
    
    protected function setUp(): void
    {
        $this->db = \Neo\Neo::initDatabase(MYSQL_CONFIG);
    }

    /**
     * @param string $msg
     */
    public function outlog($msg)
    {
        $time = date('Y-m-d H:i:s', time());
        echo "{$time} {$msg}" . PHP_EOL;
    }

    public function testUser(){
        $user = $this->db->fetchRow('select * from user where userid = 1');
        $this->assertEquals('zgia', $user['username']);

        $account = $this->db->fetchOne('select count(*) from user');
        $this->assertEquals(17, $account);
    }
}
