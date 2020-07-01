<?php

namespace Neo\Database;

use Neo\Exception\DatabaseException;
use Neo\Http\Request;
use Neo\NeoLog;

/**
 * Class to interface with a database
 */
abstract class NeoDatabase
{
    /**
     * 数据库配置
     *
     * @var array
     */
    protected $config;

    /**
     * 数据库连接
     *
     * @var null
     */
    protected $connection;

    /**
     * 查询语句
     *
     * @var string
     */
    protected $sql = '';

    /**
     * 数据库错误信息
     *
     * @var string
     */
    protected $error = '';

    /**
     * 数据库错误码
     *
     * @var int
     */
    protected $errno = '';

    /**
     * 某次请求的查询次数
     *
     * @var int
     */
    protected $querycount = 0;

    /**
     * 某次请求的所有查询语句
     *
     * @var array
     */
    protected $queryarray = [];

    /**
     * 表的前缀
     * @var string
     */
    protected $tablePrefix;

    /**
     * 事务状态 是否开始
     * @var bool
     */
    protected $trans = false;

    /**
     * 常驻内存
     *
     * @var bool
     */
    protected $memory_resident = false;

    /**
     * 是够强制走主库
     *
     * @var bool
     */
    protected $fromMaster = false;

    /**
     * 使用数据库前端代理
     *
     * @var bool
     */
    protected $use_db_proxy = false;

    /**
     * NeoDatabase constructor
     */
    public function __construct()
    {
    }

    /**
     * 返回数据库配置
     *
     * @return array
     */
    public function getConfig()
    {
        return (array) $this->config;
    }

    /**
     * 连接到一个数据库
     *
     * @param array $config Database config
     */
    public function connect(array $config)
    {
        $config['port'] || $config['port'] = 3306;

        $this->config = $config;
    }

    /**
     * 创建一个数据库连接
     *
     * @param array $config Database config
     *
     * @return bool
     */
    abstract protected function getConnection(array $config);

    /**
     * 在主数据库上执行的"写"操作，比如：insert，update，delete等等
     *
     * @param string $sql The text of the SQL query to be executed
     *
     * @return string
     */
    abstract public function write(string $sql);

    /**
     * 在从/只读数据库上执行的"读"操作，比如：select
     *
     * @param string $sql The text of the SQL query to be executed
     *
     * @return string
     */
    abstract public function read(string $sql);

    /**
     * 在主数据库上开启事务
     */
    abstract public function beginTransaction();

    /**
     * 在主数据库上回滚事务
     */
    abstract public function rollBack();

    /**
     * 在主数据库上提交事务
     */
    abstract public function commit();

    /**
     * 查询一条记录，返回关联数组格式
     *
     * @param string $sql The text of the SQL query to be executed
     *
     * @throws DatabaseException
     * @return array
     */
    abstract public function first(string $sql);

    /**
     * 查询一个值，比如返回某张表的行数
     *
     * @param string $sql The text of the SQL query to be executed
     *
     * @throws DatabaseException
     * @return false|mixed
     */
    abstract public function single(string $sql);

    /**
     * 查询多条记录，返回关联数组
     *
     * @param string $sql     The text of the SQL query to be executed
     * @param string $element array element, if null,return all element in row
     * @param string $key     array key
     *
     * @return array
     */
    abstract public function fetchArray(string $sql, string $element = null, string $key = null);

    /**
     * 返回某次查询的记录条数
     *
     * @param mixed $result The query result ID we are dealing with
     *
     * @return int
     */
    abstract public function numRows($result);

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
    abstract public function affectedRows();

    /**
     * 有自增字段的表的最后一次插入数据后的自增ID
     *
     * @return int
     */
    abstract public function insertId();

    /**
     * 释放查询结果
     *
     * @param mixed $result The query result
     */
    abstract public function freeResult($result);

    /**
     * 返回表结构
     *
     * @param string $table Table name
     *
     * @return array Fields in table. Keys are name and values are type
     */
    abstract public function describe(string $table);

    /**
     * 返回创建表的语句
     *
     * @param string $table Table name
     *
     * @return string Create table sql
     */
    abstract public function showCreateTable(string $table);

    /**
     * 返回数据库错误信息
     *
     * @return string
     */
    abstract public function getError();

    /**
     * 返回数据库错误码
     *
     * @return int
     */
    abstract public function getErrno();

    /**
     * 移除表名中不符合的字符
     *
     * @param string $table
     *
     * @return string
     */
    public function cleanTableName(string $table)
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $table);
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
        $errortext = preg_replace('/[\r\n]/', ' ', $this->sql);

        $this->sql = '';

        // 记录到日志
        $dberr = self::generateErrorData($ex->getMessage(), $ex->getCode());
        $dberr['SQL'] = $errortext;
        NeoLog::error('db', 'InvalidSQL', $dberr);

        $this->rollback();

        // 抛出
        $ex->setMore($dberr);

        throw $ex;
    }

    /**
     * 生成数据库错误信息数组
     *
     * @param string $error
     * @param int    $errno
     *
     * @return array
     */
    public static function generateErrorData(string $error, int $errno)
    {
        return [
            'error' => $error,
            'errno' => $errno,
            'time' => formatLongDate(),
            'script' => html_entity_decode(Request::uri()),
            'referer' => Request::referer(),
            'ip' => Request::ip(),
        ];
    }

    /**
     *  批量插入
     *
     * @param string $table   Name of the table into which data should be inserted
     * @param array  $data    Array of SQL values
     * @param bool   $replace INSERT or REPLACE
     * @param int    $limit
     *
     * @return bool|int
     */
    public function batchInsert(string $table, array $data, bool $replace = false, int $limit = 20)
    {
        if (! $data) {
            return false;
        }

        // 获取表插入field，防止分批写入多次获取
        $fields = array_keys(current($data));
        if (! is_array($fields)) {
            return false;
        }

        $action = $replace ? 'REPLACE' : 'INSERT';

        $sqlPre = "{$action} INTO {$this->getTablePrefix()} {$table}" . ' (' . implode(',', $fields) . ') VALUES ';

        if (! $limit || count($data) <= $limit) {
            return $this->doBatchInsert($sqlPre, $data);
        }

        $affectedRows = 0;
        $array = array_chunk($data, $limit, true);
        foreach ($array as $i => $item) {
            $affectedRows += $this->doBatchInsert($sqlPre, $item);
        }

        return $affectedRows;
    }

    /**
     * 保存批量插入
     *
     * @param string $sql  Sql INSERT | UPDATE
     * @param array  $data
     *
     * @return int
     */
    protected function doBatchInsert(string $sql, array $data)
    {
        $sql = $sql . $this->batchAssignmentSQL($sql, $data);

        return $this->write($sql);
    }

    /**
     * 数组转换为批量表字段赋值
     *
     * @param string $sqlPre 前置Sql
     * @param array  $arr
     *
     * @return string
     */
    abstract public function batchAssignmentSQL(string $sqlPre, array $arr);

    /**
     * 数组转换为表字段赋值
     *
     * @param array $arr
     *
     * @return string
     */
    abstract public function assignmentSQL(array $arr);

    /**
     * 根据条件生成SQL
     *
     * SELECT
     *      [ALL | DISTINCT | DISTINCTROW ]
     *      [HIGH_PRIORITY]
     *      [MAX_STATEMENT_TIME = N]
     *      [STRAIGHT_JOIN]
     *      [SQL_SMALL_RESULT] [SQL_BIG_RESULT] [SQL_BUFFER_RESULT]
     *      [SQL_CACHE | SQL_NO_CACHE] [SQL_CALC_FOUND_ROWS]
     * select_expr [, select_expr ...]
     * [FROM table_references
     *      [PARTITION partition_list]
     * [WHERE where_condition]
     * [GROUP BY {col_name | expr | position}
     *      [ASC | DESC], ... [WITH ROLLUP]]
     * [HAVING where_condition]
     * [ORDER BY {col_name | expr | position}
     *      [ASC | DESC], ...]
     * [LIMIT {[offset,] row_count | row_count OFFSET offset}]
     *
     * 下面中的 [] 表示可选项,不是表示数组
     * $more = array(
     *      'selectext' => [ALL | DISTINCT | DISTINCTROW ]
     *                  [HIGH_PRIORITY]
     *                  [MAX_STATEMENT_TIME = N]
     *                  [STRAIGHT_JOIN]
     *                  [SQL_SMALL_RESULT] [SQL_BIG_RESULT] [SQL_BUFFER_RESULT]
     *                  [SQL_CACHE | SQL_NO_CACHE] [SQL_CALC_FOUND_ROWS],
     *      'field'     => ['a.xxx, b.yyy, c.zzz'],
     *      'from'      => ['tablea AS a'],
     *      'left'      => [array('tableb AS b on b.id = a.id') | 'tableb AS b on b.id = a.id'],
     *      'inner'     => [array('tablec AS c on c.id = a.id') | 'tablec AS c on c.id = a.id'],
     *      'partition' => ['partition_list'],
     *      'groupby'   => ['GROUP BY xxx'],
     *      'having'    => ['HAVING xxx'],
     *      'orderby'   => ['ORDER BY xxx'],
     *      'limit'     => [array(offset, perpage) | offset]
     * )
     *
     * JOIN == CROSS JOIN == INNER JOIN，同义词。这里只使用 INNER JOIN
     *
     * @param array $conditions 条件
     * @param array $more       ORDER BY, LIMIT, GROUP BY 等等
     * @param array $ret        指定返回的数组元素
     *
     * @return string
     */
    public function selectSQL(array $conditions = [], array $more = [], array $ret = [])
    {
        // SELECT 的扩展: DISTINCT, SQL_CALC_FOUND_ROWS, STRAIGHT_JOIN 等等
        $selectext = $more['selectext'] ?? '';

        // 查询内容
        $field = $more['field'] ?? '';
        if (! $field) {
            if ($ret['e']) {
                $field = $ret['e'] . ', ' . $ret['k'];
            } elseif ($ret['es']) {
                $field = $ret['es'] . ', ' . $ret['k'];
            } else {
                $field = '*';
            }
        }

        // FROM 字句
        $from = $this->getTablePrefix() . $more['from'];

        // 分区
        $partition = $more['partition'] ? "PARTITION {$more['partition']}" : '';

        // WHERE 字句
        $where = $conditions ? $this->where($conditions) : '';

        // GROUP BY 字句
        $groupby = $more['groupby'] ? "GROUP BY {$more['groupby']}" : '';

        // HAVING 字句
        $having = $more['having'] ? "HAVING {$more['having']}" : '';

        // ORDER BY 字句
        $orderby = $more['orderby'] ? "ORDER BY {$more['orderby']}" : '';

        // LIMIT 字句
        $limit = '';
        if (isset($more['limit'])) {
            if (is_array($more['limit'])) {
                $more['offset'] = (int) $more['limit'][0];
                $more['perpage'] = (int) $more['limit'][1];
                $more['perpage'] < 1 && $more['perpage'] = 20;

                $limit = $more['offset'] < 0 ? '' : "LIMIT {$more['offset']}, {$more['perpage']}";
            } else {
                $limit = $more['limit'] ? "LIMIT {$more['limit']}" : '';
            }
        }

        // JOIN 字句
        $joined = '';
        $actions = ['left', 'left outer', 'inner', 'straight'];

        // 按照$more里面的顺序输出
        foreach (array_intersect(array_keys($more), $actions) as $j) {
            $act = $j === 'straight' ? 'STRAIGHT_JOIN' : strtoupper($j) . ' JOIN ';

            foreach ((array) $more[$j] as $table) {
                $joined .= ' ' . $act . $this->getTablePrefix() . $table;
            }
        }

        return "SELECT {$selectext} {$field} FROM {$from} {$joined} {$partition} {$where} {$groupby} {$having} {$orderby} {$limit}";
    }

    /**
     * 生成WHERE字句
     *
     * @param array  $arr
     * @param string $glue
     * @param bool   $where
     *
     * @return string
     */
    abstract public function where(?array $arr = null, string $glue = 'AND', bool $where = true);

    /**
     * 某次请求的查询次数
     *
     * @return int
     */
    abstract public function getQueryCount();

    /**
     * 某次请求的所有查询语句
     *
     * @return array
     */
    abstract public function getQueryArray();

    /**
     * 获取表前缀
     *
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * 设置表前缀
     *
     * @param string $prefix
     */
    public function setTablePrefix(string $prefix)
    {
        $this->tablePrefix = $prefix;
    }

    /**
     * LIKE 字句转义处理
     *
     * @param string $string The string to be escaped
     *
     * @return string
     */
    public function escapeLikeString(?string $string)
    {
        return str_replace(['%', '_'], ['\%', '\_'], htmlentities($string));
    }

    /**
     * Tests whether the string has an SQL operator
     *
     * @param string $str
     *
     * @return bool
     */
    public function hasOperator(string $str)
    {
        return (bool) preg_match(
            '/(<|>|!|=|\sIS NULL|\sIS NOT NULL|\sEXISTS|\sBETWEEN|\sLIKE|\sIN\s*\(|\s)/i',
            trim($str)
        );
    }

    /**
     * Returns the SQL string operator
     *
     * @param string $str
     *
     * @return string
     */
    public function getOperator(string $str)
    {
        $_operators = [
            '\s*(?:<|>|!)?=\s*',  // =, <=, >=, !=
            '\s*<>?\s*',    // <, <>
            '\s*>\s*',      // >
            '\s+IS NULL',      // IS NULL
            '\s+IS NOT NULL',     // IS NOT NULL
            '\s+EXISTS',    // EXISTS(sql)
            '\s+NOT EXISTS',   // NOT EXISTS(sql)
            '\s+BETWEEN',      // BETWEEN value AND value
            '\s+IN',     // IN(list)
            '\s+NOT IN',    // NOT IN (list)
            '\s+LIKE',      // LIKE '%expr%'
            '\s+LEFT LIKE',      // LIKE '%expr'
            '\s+RIGHT LIKE',      // LIKE 'expr%'
            '\s+NOT LIKE',      // NOT LIKE 'expr'
        ];

        return preg_match('/' . implode('|', $_operators) . '/i', $str, $match) ? trim($match[0]) : false;
    }

    /**
     * 设置常驻内存
     *
     * @param bool $mr
     */
    public function setMemoryResident(bool $mr = false)
    {
        $this->memory_resident = $mr;
    }

    /**
     * 是否常驻内存
     *
     * @return bool
     */
    public function isMemoryResident()
    {
        return $this->memory_resident;
    }

    /**
     * 设置是否从主数据库获取数据
     *
     * @param bool $fromMaster
     */
    public function setFromMaster(bool $fromMaster = false)
    {
        $this->fromMaster = $fromMaster;
    }

    /**
     * 是否从主数据库获取数据
     *
     * @return bool
     */
    public function getFromMaster()
    {
        return $this->fromMaster;
    }

    /**
     * 设置是否使用代理
     *
     * @param bool $proxy
     */
    public function setUseDBProxy(bool $proxy = false)
    {
        $this->use_db_proxy = $proxy;
    }

    /**
     * 是否使用代理
     *
     * @return bool
     */
    public function getUseDBProxy()
    {
        return $this->use_db_proxy;
    }

    /**
     * 使用hint强制走主库
     *
     * @param string $sql
     * @param string $hint
     *
     * @return string
     */
    public function forceMaster(string $sql, string $hint = '/*FORCE_MASTER*/')
    {
        if ($this->getFromMaster() && $this->getUseDBProxy() && $sql[0] != '/') {
            $sql = $hint . ' ' . $sql;
        }

        return $sql;
    }
}
