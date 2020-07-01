<?php

namespace Neo\Database;

use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\ParameterType;
use Neo\Exception\DatabaseException;

/**
 * Class to interface with a database
 */
class PdoMySQL extends NeoDatabase
{
    /**
     * 数据库连接
     *
     * @var null|DoctrineConnection
     */
    protected $connection;

    /**
     * Slave Database
     *
     * @var array
     */
    public $slaveConfig = [];

    /**
     * The parameters to bind to the query
     *
     * @var array
     */
    protected $binds = [];

    /**
     * The types of parameters to bind to the query
     *
     * @var array
     */
    protected $bindTypes = [];

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
     * NeoDatabase destructor
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * {@inheritdoc}
     */
    public function connect(array $config)
    {
        parent::connect($config);

        try {
            $this->connection = $this->getConnection($config);
        } catch (\Exception $ex) {
            $this->halt(new DatabaseException($ex->getMessage(), $ex->getCode(), $ex));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param array $config Database config
     *
     * @throws DBALException
     * @return bool|DoctrineConnection
     */
    protected function getConnection(array $config)
    {
        $configuration = new \Doctrine\DBAL\Configuration();

        if (! $this->isMemoryResident() && $config['logger'] && class_exists($config['logger'])) {
            $configuration->setSQLLogger(new $config['logger']());
        }

        /*
         * Master-Slave Connection
         *
         * $conn = DriverManager::getConnection(array(
         *    'wrapperClass' => 'Doctrine\DBAL\Connections\MasterSlaveConnection',
         *    'driver' => 'pdo_mysql',
         *    'master' => array('user' => '', 'password' => '', 'host' => '', 'dbname' => ''),
         *    'slaves' => array(
         *        array('user' => 'slave1', 'password', 'host' => '', 'dbname' => ''),
         *        array('user' => 'slave2', 'password', 'host' => '', 'dbname' => ''),
         *    )
         * ));
         *
         */

        if ($this->slaveConfig) {
            $config = [
                'wrapperClass' => 'Doctrine\DBAL\Connections\MasterSlaveConnection',
                'driver' => $config['driver'],
                'master' => $config,
                'slaves' => [$this->slaveConfig],
            ];
        }

        return \Doctrine\DBAL\DriverManager::getConnection($config, $configuration);
    }

    /**
     * {@inheritdoc}
     */
    protected function doBatchInsert(string $sql, array $data)
    {
        $this->clearBinds();

        return parent::doBatchInsert($sql, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function batchAssignmentSQL(string $sqlPre, array $arr)
    {
        $values = [];
        foreach ($arr as $data) {
            $curVal = [];
            foreach ($data as $key => $val) {
                // 非标量类型，抛出异常
                if (! is_scalar($val)) {
                    $this->sql = $sqlPre;
                    $this->halt(new DatabaseException("Non-scalar argument provided for val , key：{$key}"));
                }

                if (is_numeric($val)) {
                    $curVal[] = $val;
                } else {
                    $curVal[] = '?';
                    $this->binds[] = $val;
                    $this->bindTypes[] = $this->getBindType($val);
                }
            }
            $values[] = '(' . implode(',', $curVal) . ')';
        }

        return implode(',', $values);
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

        $sql = "{$action} INTO " . $this->getTablePrefix() . "{$table} SET " . $this->assignmentSQL($data);

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

        $sql = 'UPDATE ' . $this->getTablePrefix() . "{$table} SET " . $this->assignmentSQL($data) . $this->where($conditions);

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
            $this->sql = 'DELETE FROM ' . $this->getTablePrefix() . $table . $this->where($conditions);

            return $this->connection->delete($this->getTablePrefix() . $table, $conditions, $this->bindTypes);
        } catch (\Exception $ex) {
            $this->halt(new DatabaseException($ex->getMessage(), $ex->getCode(), $ex));
        }

        return -1;
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $sql)
    {
        $this->reading = false;

        $this->rowCount = $this->execute($sql);

        return $this->affectedRows();
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function first(string $sql)
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
     * {@inheritdoc}
     */
    public function single(string $sql)
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
     * {@inheritdoc}
     */
    public function fetchArray(string $sql, ?string $element = null, ?string $key = null)
    {
        $stmt = $this->read($sql);
        $data = $stmt->fetchAll(\Doctrine\DBAL\FetchMode::ASSOCIATIVE);
        $this->freeResult($stmt);

        $element || $element = null;
        $key || $key = null;

        return $key || $element ? array_column($data, $element, $key) : $data;
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function numRows($result = null)
    {
        return (int) $this->numRows;
    }

    /**
     * {@inheritdoc}
     */
    public function insertId()
    {
        return $this->connection->lastInsertId();
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->connection->close();

        return 1;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $sql
     *
     * @return bool|int|ResultStatement|string
     */
    protected function execute(string $sql = null)
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
     * {@inheritdoc}
     */
    public function describe(string $table)
    {
        return $this->connection->fetchAll('DESCRIBE ' . $this->cleanTableName($table));
    }

    /**
     * {@inheritdoc}
     */
    public function showCreateTable(string $table)
    {
        $sql = '';

        try {
            $data = $this->connection->fetchAssoc('SHOW CREATE TABLE ' . $this->cleanTableName($table));

            $sql = $data['Create Table'] ?? '';
        } catch (\Exception $ex) {
            // ignore
        }

        return $sql;
    }

    /**
     * 返回带单引号的转义过的 LIKE 字符串
     *
     * @param string $string  The string to be escaped
     * @param string $percent a:%string%, l:%string, r:string%
     *
     * @return string
     */
    public function elike(?string $string, string $percent = 'a')
    {
        $l = $percent == 'a' || $percent == 'l' ? '%' : '';
        $r = $percent == 'a' || $percent == 'r' ? '%' : '';

        return $l . $this->escapeLikeString($string) . $r;
    }

    /**
     * {@inheritdoc}
     */
    public function getError()
    {
        $error = $this->connection->errorInfo();

        $this->error = "({$error[0]}/{$error[1]}): {$error[2]}";

        return $this->error;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrno()
    {
        $this->errno = $this->connection->errorCode();

        return $this->errno;
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
     * {@inheritdoc}
     */
    public function assignmentSQL(array $arr)
    {
        $sql = [];

        foreach ($arr as $key => $value) {
            // 如果$key是数字，比如： array('invalid' => 0, '1' => "quality=quality+1")
            if (is_numeric($key)) {
                // 不进行转义处理
                $sql[] = $value;
            } else {
                $sql[] = $key . ' = ?';

                $this->binds[] = $value;
                $this->bindTypes[] = $this->getBindType($value);
            }
        }

        return ' ' . implode(', ', $sql);
    }

    /**
     * {@inheritdoc}
     */
    public function where(?array $arr = null, string $glue = 'AND', bool $where = true)
    {
        if (! $arr || ! is_array($arr)) {
            return '';
        }

        $sql = [];

        foreach ($arr as $key => $value) {
            $op = $this->getOperator($key);

            if ($op) {
                $key = trim(str_replace($op, '', $key));
                $op = strtoupper($op);
            }

            if (is_array($value)) {
                // 特例
                if ($op == 'BETWEEN') {
                    $sql[] = "{$key} {$op} ? AND ?";

                    $this->binds[] = $value[0];
                    $this->bindTypes[] = $this->getBindType($value[0]);
                    $this->binds[] = $value[1];
                    $this->bindTypes[] = $this->getBindType($value[1]);
                } else {
                    $op || $op = 'IN';

                    $sql[] = "{$key} {$op} (?)";

                    $this->binds[] = $value;
                    $this->bindTypes[] = $this->getBindType($value);
                }
            } else {
                // 如果$key是数字，则表示条件中含有"<>, !="等非等号(=)的判断
                // 比如： array('invalid' => 0, '1' => "image <> ''")
                // 这里的转义处理稍微复杂，如果需要转义，请在$arr传值之前处理。
                if (is_numeric($key)) {
                    // 不进行转义处理
                    $sql[] = $value;
                } else {
                    if ($op == 'IN' || $op == 'NOT IN' || $op == 'EXISTS' || $op == 'NOT EXISTS') {
                        $sql[] = "{$key} {$op} (?)";

                        $this->binds[] = $value;
                        $this->bindTypes[] = $this->getBindType($value);
                    } elseif ($op == 'LIKE' || $op == 'LEFT LIKE' || $op == 'RIGHT LIKE' || $op == 'NOT LIKE') {
                        $percent = 'a';
                        if ($op == 'LEFT LIKE' || $op == 'RIGHT LIKE') {
                            $percent = $op == 'LEFT LIKE' ? 'l' : 'r';

                            $op = 'LIKE';
                        }

                        $sql[] = "{$key} {$op} ?";

                        $this->binds[] = $this->elike($value, $percent);
                        $this->bindTypes[] = $this->getBindType($value);
                    } else {
                        $op || $op = '=';

                        $sql[] = "{$key} {$op} ?";

                        $this->binds[] = $value;
                        $this->bindTypes[] = $this->getBindType($value);
                    }
                }
            }
        }

        return ($where ? ' WHERE' : '') . ' ' . implode(' ' . $glue . ' ', $sql);
    }

    /**
     * The types of parameters to bind to the query
     *
     * @param $param
     *
     * @return int
     */
    protected function getBindType($param)
    {
        if (is_array($param)) {
            foreach ($param as $p) {
                if (! is_int($p)) {
                    return DoctrineConnection::PARAM_STR_ARRAY;
                }
            }

            return DoctrineConnection::PARAM_INT_ARRAY;
        }
        if (is_int($param)) {
            return ParameterType::INTEGER;
        }

        return ParameterType::STRING;
    }

    /**
     * Clear binds
     */
    public function clearBinds()
    {
        $this->binds = [];
        $this->bindTypes = [];
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
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        $this->fromMaster = true;
        $this->trans = true;

        $this->connection->beginTransaction();
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
        return $this->connection->getConfiguration()
            ->getSQLLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryCount()
    {
        return count($this->getQueryArray());
    }

    /**
     * {@inheritdoc}
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
