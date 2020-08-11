<?php

namespace Neo\Database;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Class to interface with a database
 */
class MySQL extends AbstractDatabase implements DatabaseInterface
{
    /**
     * 数据库连接
     *
     * @var null|DoctrineConnection
     */
    protected $connection;

    /**
     * Affected rows
     *
     * @var int
     */
    protected $rowCount = 0;

    /**
     * Rows number of SELECT result
     *
     * @var int
     */
    protected $numRows = 0;

    /**
     * Read SQL, such as SELECT
     *
     * @var bool
     */
    protected $reading = false;

    /**
     * @var QueryBuilder
     */
    protected $qb;

    /**
     * MySQL destructor
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * 启用QueryBuilder
     *
     * @return QueryBuilder
     */
    public function queryBuilder()
    {
        return $this->qb;
    }

    /**
     * 连接到一个数据库
     *
     * @param array $config Database config
     */
    public function connect(array $config)
    {
        $config = parent::parseConfig($config);

        try {
            $this->connection = $this->getConnection($config);

            // 使用QueryBuilder生成SQL
            $this->qb = $this->connection->createQueryBuilder();
        } catch (\Exception $ex) {
            $this->halt(new DatabaseException($ex->getMessage(), $ex->getCode(), $ex));
        }
    }

    /**
     * 创建一个数据库连接
     *
     * @param array $config Database config
     *
     * @throws DBALException
     * @return bool|DoctrineConnection
     */
    public function getConnection(array $config)
    {
        $configuration = new Configuration();

        if (! empty($config['logger'])) {
            if (class_exists($config['logger'])) {
                $configuration->setSQLLogger(new $config['logger']());
            }

            unset($config['logger']);
        }

        return DriverManager::getConnection($config, $configuration);
    }

    /**
     * 执行 INSERT INTO 语句
     *
     * @param string $table   Name of the table into which data should be inserted
     * @param array  $data    Array of SQL values
     * @param bool   $replace INSERT or REPLACE
     *
     * @return int
     */
    public function insert(string $table, array $data, bool $replace = false)
    {
        $this->clearBinds();

        $action = $replace ? 'REPLACE' : 'INSERT';

        $sql = "{$action} INTO " . $this->tableName($table) . ' SET ' . $this->assignmentList($data);

        return $this->write($sql);
    }

    /**
     * 执行 UPDATE 语句
     *
     * @param string $table
     * @param array  $data
     * @param array  $conditions
     *
     * @return int the number of affected rows
     */
    public function update(string $table, array $data, ?array $conditions = null)
    {
        $this->clearBinds();

        $sql = 'UPDATE ' . $this->tableName($table) . ' SET ' . $this->assignmentList($data) . $this->where($conditions);

        return $this->write($sql);
    }

    /**
     * 执行 DELETE 语句
     *
     * @param string $table
     * @param array  $conditions
     *
     * @throws DatabaseException
     * @return int               the number of affected rows
     */
    public function delete(string $table, array $conditions)
    {
        $this->clearBinds();

        try {
            $this->sql = 'DELETE FROM ' . $this->tableName($table) . $this->where($conditions);

            return $this->connection->delete($this->tableName($table), $conditions, $this->bindTypes);
        } catch (\Exception $ex) {
            $this->halt(new DatabaseException($ex->getMessage(), $ex->getCode(), $ex));
        }

        return -1;
    }

    /**
     * 在主数据库上执行的"写"操作，比如：insert，update，delete等等
     *
     * @param string $sql The text of the SQL query to be executed
     *
     * @return int
     */
    public function write(string $sql)
    {
        $this->reading = false;

        $this->rowCount = $this->execute($sql);

        return $this->affectedRows();
    }

    /**
     * 在从/只读数据库上执行的"读"操作，比如：select
     *
     * @param string $sql The text of the SQL query to be executed
     *
     * @return ResultStatement
     */
    public function read(string $sql)
    {
        $this->reading = true;

        return $this->execute($sql);
    }

    /**
     * 查询一条记录，返回关联数组格式
     *
     * @param string $sql The text of the SQL query to be executed
     *
     * @throws DatabaseException
     * @return array
     */
    public function fetchRow(string $sql)
    {
        try {
            $this->sql = trim($sql);

            return $this->connection->fetchAssoc($sql, $this->binds, $this->bindTypes);
        } catch (\Exception $ex) {
            $this->halt(new DatabaseException($ex->getMessage(), $ex->getCode(), $ex));
        }

        return null;
    }

    /**
     * 查询一个值，比如返回某张表的行数
     *
     * @param string $sql The text of the SQL query to be executed
     *
     * @throws DatabaseException
     * @return false|mixed
     */
    public function fetchOne(string $sql)
    {
        try {
            $this->sql = trim($sql);

            return $this->connection->fetchColumn($this->sql, $this->binds, 0, $this->bindTypes);
        } catch (\Exception $ex) {
            $this->halt(new DatabaseException($ex->getMessage(), $ex->getCode(), $ex));
        }

        return null;
    }

    /**
     * 查询多条记录，返回关联数组
     *
     * @param string $sql     The text of the SQL query to be executed
     * @param string $element array element, if null,return all element in row
     * @param string $key     array key
     *
     * @return array
     */
    public function fetchArray(string $sql, ?string $element = null, ?string $key = null)
    {
        $stmt = $this->read($sql);
        $data = $stmt->fetchAll(FetchMode::ASSOCIATIVE);
        $this->freeResult($stmt);

        $element || $element = null;
        $key || $key = null;

        return $key || $element ? array_column($data, $element, $key) : $data;
    }

    /**
     * 获取多行数据，返回对象
     *
     * @param string $sql        语句
     * @param string $class_name 对象类名
     *
     * @return array
     */
    public function fetchObjectArray(string $sql, $class_name = 'stdClass')
    {
        try {
            $this->sql = trim($sql);

            $class_name || $class_name = 'stdClass';

            /**
             * @var ResultStatement $rs
             */
            $rs = $this->connection->executeQuery($sql, $this->binds, $this->bindTypes);

            $rs->setFetchMode(FetchMode::CUSTOM_OBJECT, $class_name);

            return $rs->fetchAll();
        } catch (\Exception $ex) {
            $this->halt(new DatabaseException($ex->getMessage(), $ex->getCode(), $ex));
        }

        return null;
    }

    /**
     * 获取某行数据，返回对象
     *
     * @param string $sql        语句
     * @param string $class_name Object Name
     *
     * @return null|object
     */
    public function fetchObject(string $sql, $class_name = 'stdClass')
    {
        try {
            $this->sql = trim($sql);

            $class_name || $class_name = 'stdClass';

            /**
             * @var ResultStatement $rs
             */
            $rs = $this->connection->executeQuery($sql, $this->binds, $this->bindTypes);

            $rs->setFetchMode(FetchMode::CUSTOM_OBJECT, $class_name);

            return $rs->fetch();
        } catch (\Exception $ex) {
            $this->halt(new DatabaseException($ex->getMessage(), $ex->getCode(), $ex));
        }

        return null;
    }

    /**
     * 释放查询结果
     *
     * @param ResultStatement $result The query result statement
     */
    public function freeResult($result)
    {
        $result->closeCursor();
    }

    /**
     * 返回"写"操作：INSERT, UPDATE, REPLACE, DELETE 的影响行数
     *
     * 大于0的整数：影响行数
     * 0：根据条件没有更新到数据，或者语句没有执行
     * -1：出错了
     *
     * @see https://secure.php.net/manual/en/mysqli.affected-rows.php
     *
     * @return int
     */
    public function affectedRows()
    {
        return (int) $this->rowCount;
    }

    /**
     * 分页查看时，如果 SELECT 语句含 SQL_CALC_FOUND_ROWS，可以使用这个方法获取当前条件下的总行数
     *
     * @throws DatabaseException
     * @return int
     */
    public function foundRows()
    {
        try {
            return $this->connection->fetchColumn('SELECT FOUND_ROWS() AS foundRows');
        } catch (\Exception $ex) {
            $this->halt(new DatabaseException($ex->getMessage(), $ex->getCode(), $ex));
        }

        return -1;
    }

    /**
     * 返回某次查询的记录条数
     *
     * @return int
     */
    public function numRows()
    {
        return (int) $this->numRows;
    }

    /**
     * 有自增字段的表的最后一次插入数据后的自增ID
     *
     * @return int
     */
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if ($this->connection !== null) {
            $this->connection->close();

            return 1;
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $sql
     *
     * @return bool|int|ResultStatement|string
     */
    public function execute(string $sql = null)
    {
        // 强制从主库查询，且使用代理
        $this->sql = $this->forceMaster(trim($sql));

        try {
            if ($this->reading) {
                $queryresult = $this->connection->executeQuery($this->sql, $this->binds, $this->bindTypes);

                $this->numRows = $queryresult->columnCount();
            } else {
                $queryresult = $this->connection->executeUpdate($this->sql, $this->binds, $this->bindTypes);
            }

            $this->sql = '';

            return $queryresult;
        } catch (\Exception $ex) {
            $this->halt(new DatabaseException($ex->getMessage(), $ex->getCode(), $ex));
        }

        return false;
    }

    /**
     * 返回表结构
     *
     * @param string $table Table name
     *
     * @return array Fields in table. Keys are name and values are type
     */
    public function describe(string $table)
    {
        return $this->fetchAll('DESCRIBE ' . $this->stripTags($table));
    }

    /**
     * Explain SQL
     *
     * @param string $sql
     *
     * @return mixed[]
     */
    public function explain(string $sql)
    {
        return $this->fetchAll('EXPLAIN ' . $sql);
    }

    /**
     * 返回创建表的语句
     *
     * @param string $table Table name
     *
     * @return string Create table sql
     */
    public function showCreateTable(string $table)
    {
        $data = $this->fetchAll('SHOW CREATE TABLE ' . $this->stripTags($table));

        return $data[0]['Create Table'] ?? '';
    }

    /**
     * Prepares and executes an SQL query and returns the result as an associative array.
     *
     * @param string         $sql    the SQL query
     * @param mixed[]        $params the query parameters
     * @param int[]|string[] $types  the query parameter types
     *
     * @return mixed[]
     */
    public function fetchAll(string $sql, array $params = [], array $types = [])
    {
        return $this->connection->fetchAll($sql, $params, $types);
    }

    /**
     * Quotes a given input parameter
     *
     * @param mixed $input
     * @param int   $type
     *
     * @return mixed
     */
    public function quote($input, $type = ParameterType::STRING)
    {
        return $this->connection->quote($input, $type);
    }

    /**
     * 返回数据库错误信息
     *
     * @return string
     */
    public function getError()
    {
        $error = $this->connection->errorInfo();

        return "({$error[0]}/{$error[1]}): {$error[2]}";
    }

    /**
     * 返回数据库错误码
     *
     * @return int
     */
    public function getErrno()
    {
        return $this->connection->errorCode();
    }

    /**
     * 抛出数据库异常
     *
     * @param DatabaseException $ex Exception
     *
     * @throws DatabaseException
     */
    public function halt(DatabaseException $ex)
    {
        $this->rollBack();

        parent::halt($ex);
    }

    /**
     * 生成数据库错误信息数组
     *
     * @param string $error
     * @param int    $errno
     *
     * @return array
     */
    public function generateErrorData(string $error, int $errno)
    {
        $data = parent::generateErrorData($error, $errno);

        $data['db_error'] = $this->getError();
        $data['db_errno'] = $this->getErrno();

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function selectSQL(array $conditions = [], array $more = [], array $ret = [])
    {
        $this->clearBinds();

        return parent::selectSQL($conditions, $more, $ret);
    }

    /**
     * 是否在事务中
     *
     * @return bool
     */
    public function isTransactionActive()
    {
        return $this->connection->isTransactionActive();
    }

    /**
     * 在主数据库上开启事务
     */
    public function beginTransaction()
    {
        $this->fromMaster = true;
        $this->trans = true;

        $this->connection->beginTransaction();
    }

    /**
     * 在主数据库上回滚事务
     */
    public function rollBack()
    {
        if (! $this->trans) {
            return;
        }

        $this->fromMaster = false;
        $this->trans = false;

        try {
            $this->connection->rollBack();
        } catch (\Exception $ex) {
            throw new DatabaseException($ex->getMessage(), $ex->getCode(), $ex);
        }
    }

    /**
     * 在主数据库上提交事务
     */
    public function commit()
    {
        $this->fromMaster = false;
        $this->trans = false;

        try {
            $this->connection->commit();
        } catch (\Exception $ex) {
            throw new DatabaseException($ex->getMessage(), $ex->getCode(), $ex);
        }
    }

    /**
     * 获取日志处理
     *
     * @return null|\Doctrine\DBAL\Logging\SQLLogger
     */
    public function getSQLLogger()
    {
        return $this->connection->getConfiguration()->getSQLLogger();
    }

    /**
     * 某次请求的查询次数
     *
     * @return int
     */
    public function getQueryCount()
    {
        return count($this->getQueryArray());
    }

    /**
     * 某次请求的所有查询语句
     *
     * @return array
     */
    public function getQueryArray()
    {
        /**
         * @var Logger $logger
         */
        if ($logger = $this->getSQLLogger()) {
            return $logger->getQueries();
        }

        return [];
    }
}
