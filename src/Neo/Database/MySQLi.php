<?php

namespace Neo\Database;

use Neo\Exception\DatabaseException;

/**
 * Class MySQLi
 *
 * 复制了VBB的MySQLi类，并作改动
 */
class MySQLi extends NeoDatabase
{
    /**
     * 数据库连接
     *
     * @var null|\mysqli
     */
    protected $connection;

    /**
     * 从/读数据库连接
     *
     * @var null|\mysqli
     */
    protected $connection_slave;

    /**
     * 最后一次使用的数据库连接
     *
     * @var null|\mysqli
     */
    protected $connection_recent;

    /**
     * Slave Database
     *
     * @var array
     */
    public $slaveConfig = [];

    /**
     * 是够强制走主库
     *
     * @var bool
     */
    protected $fromMaster = false;

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
            $this->connectMaster($config);

            $this->connectSlave($this->slaveConfig);
        } catch (\Exception $ex) {
            $ex = $ex instanceof DatabaseException ? $ex : new DatabaseException($ex->getMessage(), $ex->getCode());
            $this->halt($ex);
        }
    }

    /**
     * 连接从/只读数据库
     *
     * @param array $config
     */
    protected function connectMaster(array $config)
    {
        $this->connection = $this->getConnection($config);
    }

    /**
     * 连接从/只读数据库
     *
     * @param array $config
     */
    protected function connectSlave(array $config)
    {
        if (! empty($config)) {
            $this->connection_slave = $this->getConnection($config);
        }

        if (empty($this->connection_slave)) {
            $this->connection_slave = $this->connection;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getConnection(array $config)
    {
        $link = mysqli_init();

        // enable/disable use of LOAD LOCAL INFILE
        mysqli_options($link, MYSQLI_OPT_LOCAL_INFILE, true);
        // connection timeout in seconds
        mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 2);

        // this will execute at most 5 times
        $try = 0;
        do {
            $connect = mysqli_real_connect(
                $link,
                $config['host'],
                $config['user'],
                $config['password'],
                $config['dbname'],
                $config['port']
            );
        } while (! $connect && $try++ < 5);

        if (! $connect) {
            $this->sql = 'get_db_connection';

            throw new DatabaseException(mysqli_connect_error(), mysqli_connect_errno());
        }

        if ($config['charset']) {
            mysqli_set_charset($link, $config['charset']);
        }

        return $link;
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

                $curVal[] = $this->e($val);
            }
            $values[] = '(' . implode(',', $curVal) . ')';
        }

        return implode(',', $values);
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
                $sql[] = $key . ' = ' . $this->e($value);
            }
        }

        return ' ' . implode(', ', $sql);
    }

    /**
     * 执行 INSERT INTO 语句
     *
     * @param string $table   Name of the table into which data should be inserted
     * @param array  $data    Array of SQL values
     * @param bool   $replace INSERT or REPLACE
     *
     * @return int the number of affected rows
     */
    public function insert(string $table, array $data, bool $replace = false)
    {
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
        $sql = 'UPDATE ' . $this->getTablePrefix() . "{$table} SET " . $this->assignmentSQL($data) . $this->where($conditions);

        return $this->write($sql);
    }

    /**
     * 执行 DELETE 语句
     *
     * @param string $table
     * @param array  $conditions
     *
     * @return int the number of affected rows
     */
    public function delete(string $table, array $conditions)
    {
        $sql = 'DELETE FROM ' . $this->getTablePrefix() . $table . $this->where($conditions);

        return $this->write($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $sql)
    {
        $this->connection_recent = $this->connection;

        $this->execute($sql);

        return $this->affectedRows();
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $sql)
    {
        $this->connection_recent = $this->fromMaster ? $this->connection : $this->connection_slave;

        return $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function first(string $sql)
    {
        $result = $this->read($sql);
        $returnarray = mysqli_fetch_assoc($result);
        $this->freeResult($result);

        return $returnarray;
    }

    /**
     * {@inheritdoc}
     */
    public function single(string $sql)
    {
        $result = $this->read($sql);
        $returnarray = $this->fetchRow($result);
        $this->freeResult($result);

        return $returnarray[0] ?? false;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchArray(string $sql, ?string $element = null, ?string $key = null)
    {
        $result = $this->read($sql);

        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $this->freeResult($result);

        $element || $element = null;
        $key || $key = null;

        return $element || $key ? array_column($data, $element, $key) : $data;
    }

    /**
     * {@inheritdoc}
     */
    public function numRows($queryresult)
    {
        return mysqli_num_rows($queryresult);
    }

    /**
     * 分页查看时，如果 SELECT 语句含 SQL_CALC_FOUND_ROWS，可以使用这个方法获取当前条件下的总行数
     *
     * @return int
     */
    public function foundRows()
    {
        $sql = 'SELECT FOUND_ROWS()';

        $result = $this->execute($sql);
        $returnarray = $this->fetchRow($result);
        $this->freeResult($result);

        return (int) $returnarray[0];
    }

    /**
     * {@inheritdoc}
     */
    public function affectedRows()
    {
        return mysqli_affected_rows($this->connection_recent);
    }

    /**
     * {@inheritdoc}
     */
    public function insertId()
    {
        return mysqli_insert_id($this->connection);
    }

    /**
     * 数据库连接编码
     *
     * @return string
     */
    protected function clientEncoding()
    {
        return mysqli_character_set_name($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        mysqli_close($this->connection);

        if ($this->connection_slave !== $this->connection) {
            @mysqli_close($this->connection_slave);
        }

        return 1;
    }

    /**
     * 返回带单引号的转义过的字符串
     *
     * @param mixed $string The string to be escaped
     *
     * @return mixed
     */
    public function e($string)
    {
        if (is_int($string)) {
            return $string;
        }

        return "'" . $this->escapeString($string) . "'";
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

        return "'" . $l . $this->escapeString($this->escapeLikeString($string)) . $r . "'";
    }

    /**
     * 转义字符串，防止SQL注入
     *
     * @param string $string The string to be escaped
     *
     * @return string
     */
    public function escapeString(?string $string)
    {
        return mysqli_real_escape_string($this->connection, $string);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(string $sql)
    {
        // 强制从主库查询，且使用代理
        $this->sql = $this->forceMaster(trim($sql));

        // 如果是Swoole等常驻内存型调用，则不记录SQL
        if (! $this->isMemoryResident()) {
            ++$this->querycount;
            $this->queryarray[] = $this->sql;
        }

        if ($queryresult = mysqli_query($this->connection_recent, $this->sql, MYSQLI_STORE_RESULT)) {
            $this->sql = '';

            return $queryresult;
        }

        $this->halt(new DatabaseException($this->getError(), $this->getErrno()));

        return false;
    }

    /**
     * 忽略各种处理，直接执行一条语句
     *
     * @param string $sql the sql query
     *
     * @return bool|\mysqli_result
     */
    public function simpleQuery($sql)
    {
        return mysqli_query($this->connection, trim($sql));
    }

    /**
     * {@inheritdoc}
     */
    public function describe(string $table)
    {
        $result = $this->simpleQuery('DESCRIBE ' . $this->cleanTableName($table));
        $fields = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $this->freeResult($result);

        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    public function showCreateTable(string $table)
    {
        $result = $this->simpleQuery('SHOW CREATE TABLE ' . $this->cleanTableName($table));
        $data = mysqli_fetch_assoc($result);
        $this->freeResult($result);

        return $data['Create Table'] ?? '';
    }

    /**
     * @param string $sql        语句
     * @param string $class_name 对象类名
     *
     * @return array
     */
    public function fetchObjectArray(string $sql, $class_name = 'stdClass')
    {
        $result = $this->read($sql);

        $data = [];
        while ($obj = $this->fetchObject($result, $class_name)) {
            $data[] = $obj;
        }
        $this->freeResult($result);

        return $data;
    }

    /**
     * @param string $sql        语句
     * @param string $class_name Object Name
     *
     * @return null|object
     */
    public function fetchObjectFirst(string $sql, $class_name = 'stdClass')
    {
        $result = $this->read($sql);

        $obj = $this->fetchObject($result, $class_name);
        $this->freeResult($result);

        return $obj;
    }

    /**
     * 以对象的格式返回某行查询记录
     *
     * 如果指定$class，则返回相应的类，否则返回stdClass
     *
     * @param \mysqli_result $queryresult The query result ID we are dealing with
     * @param string         $class_name  Object Name
     *
     * @return null|object
     */
    public function fetchObject($queryresult, $class_name = 'stdClass')
    {
        $class_name || $class_name = 'stdClass';

        return mysqli_fetch_object($queryresult, $class_name);
    }

    /**
     * 返回数字键数组，同: mysqli_fetch_array($result, MYSQLI_NUM)
     *
     * @param \mysqli_result $queryresult The query result ID we are dealing with
     *
     * @return array
     */
    public function fetchRow($queryresult)
    {
        return mysqli_fetch_row($queryresult);
    }

    /**
     * 释放查询结果
     *
     * @param mixed $result The query result
     */
    public function freeResult($result)
    {
        $this->sql = '';

        mysqli_free_result($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getError()
    {
        $this->error = (string) mysqli_error($this->connection_recent);

        return $this->error;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrno()
    {
        $this->errno = (int) mysqli_errno($this->connection_recent);

        return $this->errno;
    }

    /**
     * {@inheritdoc}
     */
    public function selectSQL(array $conditions = [], array $more = [], array $ret = [])
    {
        return parent::selectSQL($conditions, $more, $ret);
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
                    $sql[] = "{$key} BETWEEN {$value[0]} AND {$value[1]}";
                } else {
                    $op || $op = 'IN';
                    $sql[] = "{$key} {$op} ('" . implode("', '", array_map([$this, 'escapeString'], $value)) . "')";
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
                        $sql[] = "{$key} {$op} ({$value})";
                    } elseif ($op == 'LIKE' || $op == 'LEFT LIKE' || $op == 'RIGHT LIKE' || $op == 'NOT LIKE') {
                        $percent = 'a';
                        if ($op == 'LEFT LIKE' || $op == 'RIGHT LIKE') {
                            $percent = $op == 'LEFT LIKE' ? 'l' : 'r';

                            $op = 'LIKE';
                        }

                        $sql[] = "{$key} {$op} " . $this->elike($value, $percent);
                    } else {
                        $op || $op = '=';

                        $sql[] = "{$key} {$op} " . $this->e($value);
                    }
                }
            }
        }

        return ($where ? ' WHERE' : '') . ' ' . implode(' ' . $glue . ' ', $sql);
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryCount()
    {
        return $this->querycount;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryArray()
    {
        return $this->queryarray;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        $this->fromMaster = true;
        $this->trans = true;

        mysqli_begin_transaction($this->connection);
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

        mysqli_rollback($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $this->fromMaster = false;
        $this->trans = false;

        mysqli_commit($this->connection);
    }
}
