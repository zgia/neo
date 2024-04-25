<?php

namespace Neo\Database;

/**
 * Class to interface with a database
 */
interface DatabaseInterface
{
    /**
     * 连接到一个数据库
     *
     * @return bool TRUE: 成功建立链接, FALSE: 链接已经存在
     */
    public function connect();

    /**
     * 执行一条查询语句
     *
     * @param string $sql 待执行的语句
     *
     * @return string
     */
    public function execute(string $sql = null);

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
    public function beginTransaction(): void;

    /**
     * 在主数据库上回滚事务
     */
    public function rollBack(): void;

    /**
     * 在主数据库上提交事务
     */
    public function commit(): void;

    /**
     * 错误码
     *
     * @return int|string
     */
    public function errorCode();

    /**
     * 错误信息
     *
     * @return string
     */
    public function errorInfo();
}
