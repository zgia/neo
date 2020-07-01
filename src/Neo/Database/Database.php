<?php

namespace Neo\Database;

/**
 * Class to interface with a database
 */
interface Database
{
    /**
     * 连接到一个数据库
     *
     * @param array $config Database config
     */
    public function connect(array $config);

    /**
     * 执行一条查询语句
     *
     * @param string $sql The text of the SQL query to be executed
     *
     * @return string
     */
    public function execute(string $sql);

    /**
     * 关闭数据库连接
     *
     * @return int
     */
    public function close();

    /**
     * 是否在事务中
     *
     * @return bool
     */
    public function isTransactionActive();

    /**
     * 在主数据库上开启事务
     */
    public function beginTransaction();

    /**
     * 在主数据库上回滚事务
     */
    public function rollBack();

    /**
     * 在主数据库上提交事务
     */
    public function commit();
}
