<?php

namespace Neo\Base;

/**
 * Base Class
 * User: liyuntian
 * Date: 2015/3/17
 * Time: 10:30
 */
class NeoBase
{
    /**
     * @var \Neo\NeoFrame
     */
    protected $neo;

    /**
     * @var array
     */
    protected $user = [];

    /**
     * @var int
     */
    protected $userid = 0;

    /**
     * NeoBase constructor.
     */
    public function __construct()
    {
        $this->neo = neo();

        $this->user = $this->neo->user;
        $this->userid = $this->neo->user['userid'];
    }

    /**
     * 数据库连接
     *
     * @return \Neo\Database\MySQLi|\Neo\Database\NeoDatabase|\Neo\Database\PdoMySQL
     */
    public function getDB()
    {
        return $this->neo->getDB();
    }
}
