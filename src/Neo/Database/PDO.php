<?php

namespace Neo\Database;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\SQLParserUtils;

/**
 * Class to interface with a database
 */
class PDO extends AbstractDatabase implements DatabaseInterface
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
     * @var \Throwable Query Exception
     */
    protected $exception;

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
     * @return bool TRUE: 成功建立链接, FALSE: 链接已经存在
     */
    public function connect()
    {
        if ($this->connection !== null) {
            return false;
        }

        try {
            $this->connection = $this->createConnection($this->getConfig());

            // 使用QueryBuilder生成SQL
            $this->qb = $this->connection->createQueryBuilder();
        } catch (\Throwable $ex) {
            $this->halt($ex);
        }

        return true;
    }

    /**
     * 创建一个数据库连接
     *
     * @param array<string,mixed> $params Database config
     *
     * @return bool|DoctrineConnection
     *
     * @throws DBALException
     */
    public function createConnection(array $params)
    {
        $configuration = new Configuration();

        if (! empty($params['logger'])) {
            if (class_exists($params['logger'])) {
                $configuration->setSQLLogger(new $params['logger']());
            }

            unset($params['logger']);
        }

        return DriverManager::getConnection($params, $configuration);
    }

    /**
     * 返回当前的数据库连接
     *
     * @return null|DoctrineConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * 执行 INSERT INTO 语句
     *
     * @param string              $table 表名
     * @param array<string,mixed> $data  待插入的数据
     *
     * @return int 影响行数
     */
    public function insert(string $table, array $data)
    {
        $this->clearBinds();

        $table = $this->tableName($table);

        $this->sql = 'INSERT INTO ' . $table . ' (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_values($data)) . ')';

        return $this->connection->insert($table, $data, $this->getBindTypes());
    }

    /**
     * 批量插入数据
     *
     * @param string       $table 表名
     * @param array<mixed> $data  待插入的数据
     * @param int          $size  每次插入多少条数据
     *
     * @return int 影响行数
     */
    public function insertBulk(string $table, array $data, int $size = 50)
    {
        $rowCount = count($data);

        $table = $this->tableName($table);

        $size = max($size, 10);

        // 用于构造(?,?,?),(?,?,?),(?,?,?)...
        $funcMark = function ($mark, $size) {
            return rtrim(str_repeat($mark . ',', $size), ',');
        };

        $columns = array_keys($data[0]);
        $mark = '(' . $funcMark('?', count($columns)) . ')';

        $sqlx = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES ';

        // 每次插入 $size 条数据
        try {
            $sql = '';
            if ($rowCount >= $size) {
                $chunkedData = array_chunk($data, $size);
                $lastKey = count($chunkedData) - 1;
                if (count($chunkedData[$lastKey]) < $size) {
                    $data = $chunkedData[$lastKey];
                    unset($chunkedData[$lastKey]);
                } else {
                    unset($data);
                    $data = [];
                }

                $sql = $sqlx . $funcMark($mark, $size);

                $this->executeInsertBulk($sql, $chunkedData);
            }

            if ($data) {
                $sql = $sqlx . $funcMark($mark, count($data));

                $this->executeInsertBulk($sql, [$data]);
            }
        } catch (\Throwable $e) {
            $this->connection->handleExceptionDuringQuery($e, $sql);
        }

        return $rowCount;
    }

    /**
     * 执行批量插入
     *
     * @param string       $sql         SQL 语句
     * @param array<mixed> $chunkedData 分割后的数组
     */
    private function executeInsertBulk(string $sql, array $chunkedData): void
    {
        $connection = $this->connection->getWrappedConnection();
        $stmt = $connection->prepare($sql);

        foreach ($chunkedData as $data) {
            $this->clearBinds();

            foreach ($data as $val) {
                foreach ($val as $v) {
                    $this->bindValue($v);
                }
            }

            [$sql, $params, $types] = SQLParserUtils::expandListParameters($sql, $this->getBinds(), $this->getBindTypes());

            $bindIndex = 1;
            foreach ($params as $value) {
                $stmt->bindValue($bindIndex, $value, $types[$bindIndex - 1] ?? ParameterType::STRING);

                ++$bindIndex;
            }
            $stmt->execute();
        }
    }

    /**
     * 执行 UPDATE 语句。支持 field=field+1 的语法
     *
     * @param string              $table      表名
     * @param array<string,mixed> $data       待更新的数据
     * @param array<string,mixed> $conditions 更新条件
     *
     * @return int 影响行数
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
     * @param string              $table      表名
     * @param array<string,mixed> $conditions 删除条件
     *
     * @return int 影响行数
     *
     * @throws DatabaseException
     */
    public function delete(string $table, array $conditions)
    {
        $this->clearBinds();

        try {
            $sql = 'DELETE FROM ' . $this->tableName($table) . $this->where($conditions);

            return $this->write($sql);
        } catch (\Throwable $ex) {
            $this->halt($ex);
        }

        return -1;
    }

    /**
     * 在主数据库上执行的"写"操作，比如：insert，update，delete等等
     *
     * @param string $sql        待执行的语句
     * @param bool   $clearBinds 是否清理之前绑定的参数及类型
     *
     * @return int 影响行数
     */
    public function write(string $sql, bool $clearBinds = false)
    {
        if ($clearBinds) {
            $this->clearBinds();
        }

        $this->reading = false;

        $this->rowCount = $this->execute($sql);

        return $this->affectedRows();
    }

    /**
     * 在从/只读数据库上执行的"读"操作，比如：select
     *
     * @param string $sql        待执行的语句
     * @param bool   $clearBinds 是否清理之前绑定的参数及类型
     *
     * @return ResultStatement<mixed>
     */
    public function read(string $sql, bool $clearBinds = false)
    {
        if ($clearBinds) {
            $this->clearBinds();
        }

        $this->reading = true;

        return $this->execute($sql);
    }

    /**
     * 查询一条记录，返回关联数组格式
     *
     * @param string $sql 待执行的语句
     *
     * @return null|array<string,mixed>
     *
     * @throws DatabaseException
     */
    public function fetchRow(string $sql)
    {
        try {
            $this->sql = trim($sql);

            return $this->connection->fetchAssociative($sql, $this->getBinds(), $this->getBindTypes());
        } catch (\Throwable $ex) {
            $this->halt($ex);
        }

        return null;
    }

    /**
     * 查询一个值，比如返回某张表的行数
     *
     * @param string $sql 待执行的语句
     *
     * @return false|mixed
     *
     * @throws DatabaseException
     */
    public function fetchOne(string $sql)
    {
        try {
            $this->sql = trim($sql);

            return $this->connection->fetchOne($this->sql, $this->getBinds(), $this->getBindTypes());
        } catch (\Throwable $ex) {
            $this->halt($ex);
        }

        return null;
    }

    /**
     * 查询多条记录，返回关联数组
     *
     * @param string $sql     待执行的语句
     * @param string $element 数组元素, 如果 null，则返回数据全部内容
     * @param string $key     数组键值
     *
     * @return array<string,mixed>
     */
    public function fetchArray(string $sql, ?string $element = null, ?string $key = null)
    {
        $this->sql = trim($sql);
        $data = $this->fetchAll($this->sql, $this->getBinds(), $this->getBindTypes());

        $element || $element = null;
        $key || $key = null;

        return $key || $element ? array_column($data, $element, $key) : $data;
    }

    /**
     * Prepares and executes an SQL query and returns the result as an associative array.
     *
     * @param string         $sql    待执行的语句
     * @param mixed[]        $params 待绑定的数据
     * @param int[]|string[] $types  待绑定的数据类型
     *
     * @return mixed[]
     */
    public function fetchAll(string $sql, array $params = [], array $types = [])
    {
        return $this->connection->fetchAllAssociative($sql, $params, $types);
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
     * @return int
     *
     * @throws DatabaseException
     */
    public function foundRows()
    {
        try {
            $this->rowCount = $this->connection->fetchOne('SELECT FOUND_ROWS() AS foundRows');

            return $this->rowCount;
        } catch (\Throwable $ex) {
            $this->halt($ex);
        }

        return -1;
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
     * @param string $sql 待执行的语句
     *
     * @return bool|int|ResultStatement<mixed>|string
     */
    public function execute(string $sql = null)
    {
        // 强制从主库查询，且使用代理
        $this->sql = $this->forceMaster(trim($sql));

        try {
            if ($this->reading) {
                $queryresult = $this->connection->executeQuery($this->sql, $this->binds, $this->bindTypes);

                $this->rowCount = $queryresult->rowCount();
            } else {
                $queryresult = $this->connection->executeStatement($this->sql, $this->binds, $this->bindTypes);
            }

            $this->sql = '';

            return $queryresult;
        } catch (\Throwable $ex) {
            $this->halt($ex);
        }

        return false;
    }

    /**
     * Truncate Table
     *
     * @param string $table 表名
     */
    public function truncate(string $table): void
    {
        $this->clearBinds();

        $this->execute($this->connection->getDatabasePlatform()->getTruncateTableSQL($table));
    }

    /**
     * 返回表结构
     *
     * @param string $table 表名
     *
     * @return array<string,mixed> 表结构
     */
    public function describe(string $table)
    {
        return [];
    }

    /**
     * Explain SQL
     *
     * @param string $sql SQL 语句
     *
     * @return mixed[]
     */
    public function explain(string $sql)
    {
        return [];
    }

    /**
     * 返回创建表的语句
     *
     * @param string $table 表名
     *
     * @return null|string 创建表的语句
     */
    public function showCreateTable(string $table)
    {
        return null;
    }

    /**
     * Quotes a given input parameter
     *
     * @param mixed $input Something to be quoted
     * @param int   $type  Type of something
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
    public function errorInfo()
    {
        return $this->exception->getMessage();
    }

    /**
     * 返回数据库错误码
     *
     * @return int|string
     */
    public function errorCode()
    {
        if (method_exists($this->exception, 'getSQLState')) {
            return $this->exception->getSQLState();
        }

        return $this->exception->getCode();
    }

    /**
     * 抛出数据库异常
     *
     * @param \Throwable $ex Exception
     *
     * @throws DatabaseException
     */
    public function halt(\Throwable $ex): void
    {
        $this->exception = $ex;

        $this->rollBack();

        parent::halt(new DatabaseException($ex->getMessage(), $ex->getCode(), $ex));
    }

    /**
     * 生成数据库错误信息数组
     *
     * @param string $error 错误信息
     * @param int    $errno 错误码
     *
     * @return array<string,mixed>
     */
    public function generateErrorData(string $error, int $errno)
    {
        $data = parent::generateErrorData($error, $errno);

        $data['db_error'] = $this->errorInfo();
        $data['db_errno'] = $this->errorCode();

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
    public function beginTransaction(): void
    {
        $this->fromMaster = true;
        $this->trans = true;

        $this->connection->beginTransaction();
    }

    /**
     * 在主数据库上回滚事务
     */
    public function rollBack(): void
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
    public function commit(): void
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
     * @return null|\Doctrine\DBAL\Logging\SQLLogger|Logger
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
     * @return array<int,array<string,mixed>>
     */
    public function getQueryArray()
    {
        if ($logger = $this->getSQLLogger()) {
            return $logger->getQueries();
        }

        return [];
    }

    /**
     * 显示一些调试信息
     */
    public function display(): void
    {
        echo var_export($this->getQueryArray(), true);
    }
}
