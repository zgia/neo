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
    /**
     * @var \Neo\Database\Query\QueryBuilder
     */
    public $qb;

    protected function setUp(): void
    {
        $this->db = \Neo\NeoFrame::initDatabase(MYSQL_CONFIG);

        $this->qb = $this->db->queryBuilder();
    }

    /**
     * @param string $msg
     */
    public function outlog($msg)
    {
        $time = date('Y-m-d H:i:s', time());
        echo "{$time} {$msg}" . PHP_EOL;
    }
}
