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
     * @param array $config 数据库配置
     */
    public function connect(array $config);

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
    public function beginTransaction();

    /**
     * 在主数据库上回滚事务
     */
    public function rollBack();

    /**
     * 在主数据库上提交事务
     */
    public function commit();

    /**
     * 错误码
     *
     * @return null|string
     */
    public function errorCode();

    /**
     * 错误信息
     *
     * @return string
     */
    public function errorInfo();
}
