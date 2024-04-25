<?php

namespace Neo\Database;

use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\ParameterType;
use Neo\NeoLog;

/**
 * Class to interface with a database
 */
abstract class AbstractDatabase
{
    /**
     * 数据库配置
     *
     * @var array<string,mixed>
     */
    protected $config;

    /**
     * 查询语句
     *
     * @var string
     */
    protected $sql = '';

    /**
     * 某次请求的查询次数
     *
     * @var int
     */
    protected $querycount = 0;

    /**
     * 某次请求的所有查询语句
     *
     * @var array<string>
     */
    protected $queryarray = [];

    /**
     * 待绑定到语句中的参数
     *
     * @var array<string>
     */
    protected $binds = [];

    /**
     * 待绑定到语句中的参数的类型
     *
     * @var array<string>
     */
    protected $bindTypes = [];

    /**
     * 表的前缀
     *
     * @var string
     */
    protected $tablePrefix;

    /**
     * 事务状态 是否开始
     *
     * @var bool
     */
    protected $trans = false;

    /**
     * 是够强制走主库
     *
     * @var bool
     */
    protected $fromMaster = false;

    /**
     * 使用哪个从库
     *
     * @var int
     */
    protected $replicaIndex = 0;

    /**
     * 返回数据库配置
     *
     * @return array<string,mixed>
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * 解析数据库配置，以便支持Doctrine
     *
     * @param array<string,mixed> $config 数据库配置
     *
     * @return array<string,mixed>
     */
    public function parseConfig(array $config)
    {
        $this->setTablePrefix($config['prefix']);
        unset($config['prefix']);

        if (! empty($config['replica'])) {
            $count = count($config['replica']);
            // ip不变，从库不变
            $this->replicaIndex = $count > 1 ? ord(md5(neo()->getRequest()->getClientIp())[0]) % $count : 0;

            $config['replica'] = [$config['replica'][$this->replicaIndex]];
            $config['wrapperClass'] = 'Neo\Database\MSConnection';
        }

        $this->config = $config;

        return $config;
    }

    /**
     * 使用哪个从库
     *
     * @return int
     */
    public function getReplicaIndex()
    {
        return $this->replicaIndex;
    }

    /**
     * 执行 INSERT INTO 语句
     *
     * @param string              $table 表名
     * @param array<string,mixed> $data  待插入的数据
     *
     * @return int 影响行数
     */
    abstract public function insert(string $table, array $data);

    /**
     * 执行 UPDATE 语句
     *
     * @param string              $table      表名
     * @param array<string,mixed> $data       待更新的数据
     * @param array<string,mixed> $conditions 更新条件
     *
     * @return int 影响行数
     */
    abstract public function update(string $table, array $data, ?array $conditions = null);

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
    abstract public function delete(string $table, array $conditions);

    /**
     * 在主数据库上执行的"写"操作，比如：insert，update，delete等等
     *
     * @param string $sql        待执行的语句
     * @param bool   $clearBinds 是否清理之前绑定的参数及类型
     *
     * @return int 影响行数
     */
    abstract public function write(string $sql, bool $clearBinds = false);

    /**
     * 在从/只读数据库上执行的"读"操作，比如：select
     *
     * @param string $sql        待执行的语句
     * @param bool   $clearBinds 是否清理之前绑定的参数及类型
     *
     * @return ResultStatement<mixed>
     */
    abstract public function read(string $sql, bool $clearBinds = false);

    /**
     * 查询一条记录，返回关联数组格式
     *
     * @param string $sql 待执行的语句
     *
     * @return array<string,mixed>
     *
     * @throws DatabaseException
     */
    abstract public function fetchRow(string $sql);

    /**
     * 查询一个值，比如返回某张表的行数
     *
     * @param string $sql 待执行的语句
     *
     * @return false|mixed
     *
     * @throws DatabaseException
     */
    abstract public function fetchOne(string $sql);

    /**
     * 查询多条记录，返回关联数组
     *
     * @param string $sql     待执行的语句
     * @param string $element 数组元素, 如果 null，则返回数据全部内容
     * @param string $key     数组键值
     *
     * @return array<string,mixed>
     */
    abstract public function fetchArray(string $sql, ?string $element = null, ?string $key = null);

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
     * $conditions = array(
     *      'FIELD'            => 'abc',
     *      0                  => "FIELD [NOT ]LIKE 'def%'",
     *      1                  => 'FIELD =|<>|!=| >|<|>=|<= 123',
     *      2                  => 'FIELD IS NULL',,
     *      3                  => 'FIELD = FIELD + 1',
     *      'FIELD [NOT ]LIKE' => 'abc%',
     *      'FIELD [NOT ]IN'   => '1,3,4,5',
     *      'FIELD [NOT ]IN'   => [1,3,4,5],
     *      'FIELD BETWEEN'    => [1,3],
     * )
     *
     * @param array<string,mixed> $conditions 条件
     * @param array<string,mixed> $more       ORDER BY, LIMIT, GROUP BY 等等
     * @param array<string>       $ret        指定返回的数组元素
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
        $from = $this->tableName($more['from']);

        // JOIN 字句
        $joined = '';
        $actions = ['left', 'left outer', 'inner', 'straight'];

        // 按照$more里面的顺序输出
        foreach (array_intersect(array_keys($more), $actions) as $j) {
            $act = $j === 'straight' ? 'STRAIGHT_JOIN' : strtoupper($j) . ' JOIN ';

            foreach ((array) $more[$j] as $table) {
                $joined .= ' ' . $act . $this->tableName($table);
            }
        }

        // 分区
        $partition = empty($more['partition']) ? '' : "PARTITION {$more['partition']}";

        // WHERE 字句
        $where = $conditions ? $this->where($conditions) : '';

        // GROUP BY 字句
        $groupby = empty($more['groupby']) ? '' : "GROUP BY {$more['groupby']}";

        // HAVING 字句
        $having = empty($more['having']) ? '' : "HAVING {$more['having']}";

        // ORDER BY 字句
        $orderby = empty($more['orderby']) ? '' : "ORDER BY {$more['orderby']}";

        // LIMIT 字句
        $limit = '';
        if (! empty($more['limit'])) {
            if (is_array($more['limit'])) {
                $more['offset'] = (int) $more['limit'][0];
                $more['perpage'] = (int) $more['limit'][1];
                $more['perpage'] < 1 && $more['perpage'] = 20;

                $limit = $more['offset'] < 0 ? '' : "LIMIT {$more['offset']}, {$more['perpage']}";
            } else {
                $more['limit'] = (int) $more['limit'];
                $limit = $more['limit'] ? "LIMIT {$more['limit']}" : '';
            }
        }

        return "SELECT {$selectext} {$field} FROM {$from} {$joined} {$partition} {$where} {$groupby} {$having} {$orderby} {$limit}";
    }

    /**
     * 生成WHERE字句
     *
     * @param array<string,mixed> $conditions 条件
     * @param string              $glue       拼接为WHERE字句的关键字
     * @param bool                $withWhere  是否添加WHERE关键字
     *
     * @return string
     */
    public function where(?array $conditions = null, string $glue = 'AND', bool $withWhere = true)
    {
        if (! $conditions || ! is_array($conditions)) {
            return '';
        }

        $sql = [];

        foreach ($conditions as $key => $value) {
            $op = $this->getOperator($key);

            if ($op) {
                $key = trim(str_replace($op, '', $key));
                $op = strtoupper($op);
            }

            // NULL 特殊处理
            if ($op === 'IS NULL' || $op === 'IS NOT NULL') {
                $sql[] = "{$key} {$op}";

                continue;
            }

            if (is_array($value)) {
                if ($op === 'BETWEEN') {
                    $sql[] = "{$key} {$op} ? AND ?";

                    $this->bindValue($value[0]);
                    $this->bindValue($value[1]);
                } else {
                    $op || $op = 'IN';

                    $sql[] = "{$key} {$op} (?)";

                    $this->bindValue($value);
                }
            } else {
                // 如果$key是数字，则表示条件中含有"<>, !="等非等号(=)的判断
                // 比如： array('invalid' => 0, '1' => "image <> ''")
                // 这里的转义处理稍微复杂，如果需要转义，请在$conditions传值之前处理。
                if (is_numeric($key)) {
                    // 不进行转义处理
                    $sql[] = $value;
                } else {
                    if ($op === 'IN' || $op === 'NOT IN' || $op === 'EXISTS' || $op === 'NOT EXISTS') {
                        $sql[] = "{$key} {$op} (?)";
                    } elseif ($op === 'LIKE' || $op === 'NOT LIKE') {
                        preg_match('/(^%?)(.*?)(%?$)/', $value, $ma);

                        // 如果没有LIKE内容没有加%模式，则自动在前后增加%
                        if (! $ma[1] && ! $ma[3]) {
                            $ma[1] = '%';
                            $ma[3] = '%';
                        }

                        $value = $ma[1] . $this->escapeLike($ma[2]) . $ma[3];

                        $sql[] = "{$key} {$op} ?";
                    } else {
                        $op || $op = '=';

                        $sql[] = "{$key} {$op} ?";
                    }

                    $this->bindValue($value);
                }
            }
        }

        return ($withWhere ? ' WHERE' : '') . ' ' . implode(' ' . $glue . ' ', $sql);
    }

    /**
     * 检查待绑定的值及其类型
     *
     * @param mixed $value 待绑定的值
     */
    public function bindValue($value): void
    {
        $this->binds[] = $value;
        $this->bindTypes[] = $this->getParameterType($value);
    }

    /**
     * The types of parameters to bind to the quoteery
     *
     * @param mixed $param 待绑定的参数
     *
     * @return int
     */
    protected function getParameterType($param)
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
     * Get binds
     *
     * @return array<string>
     */
    public function getBinds()
    {
        return $this->binds;
    }

    /**
     * Get bind types
     *
     * @return array<string>
     */
    public function getBindTypes()
    {
        return $this->bindTypes;
    }

    /**
     * 设置参数绑定
     *
     * @param array<string> $binds     待绑定到语句中的参数
     * @param array<string> $bindTypes 待绑定到语句中的参数的类型
     */
    public function setBinds(array $binds, array $bindTypes): void
    {
        $this->binds = $binds;
        $this->bindTypes = $bindTypes;
    }

    /**
     * Clear binds
     */
    public function clearBinds(): void
    {
        $this->binds = [];
        $this->bindTypes = [];
    }

    /**
     * 数组转换为表字段赋值，用于insert或者update
     *
     * @param array<string,mixed> $arr 键值对
     *
     * @return string
     */
    public function assignmentList(array $arr)
    {
        $sql = [];

        foreach ($arr as $key => $value) {
            // 如果$key是数字，比如： array('invalid' => 0, '1' => "quality=quality+1")
            if (is_numeric($key)) {
                // 不进行转义处理
                $sql[] = $value;
            } else {
                $sql[] = $key . ' = ?';

                $this->bindValue($value);
            }
        }

        return ' ' . implode(', ', $sql);
    }

    /**
     * 数据库名、表名、字段名只应该含：字母、数字、.、-、_等符号
     *
     * @param string      $field     待处理的值
     * @param null|string $allowTags 附加的保留字符串
     *
     * @return string
     */
    public function stripTags(string $field, $allowTags = null)
    {
        if (empty($field)) {
            return '';
        }

        $pattern = 'a-z0-9_\-\.';
        if ($allowTags) {
            $pattern .= $allowTags;
        }

        return preg_replace("/[^{$pattern}]/i", '', $field);
    }

    /**
     * 获取表名，可能带前缀
     *
     * @param string $table 表明
     *
     * @return string
     */
    public function tableName(string $table)
    {
        return $this->getTablePrefix() . $table;
    }

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
     * @param string $prefix 表前缀
     */
    public function setTablePrefix(string $prefix): void
    {
        $this->tablePrefix = $prefix;
    }

    /**
     * 返回带单引号的转义过的 LIKE 字符串
     *
     * @param string $string 待转义的字符串
     *
     * @return string
     */
    public function escapeLike(?string $string)
    {
        return str_replace(['%', '_'], ['\%', '\_'], htmlentities($string));
    }

    /**
     * 检查一条语句中是否存在判断操作符
     *
     * @param string $sql SQL 语句
     *
     * @return bool
     */
    public function hasOperator(string $sql)
    {
        return (bool) preg_match(
            '/(<|>|!|=|\sIS NULL|\sIS NOT NULL|\sEXISTS|\sBETWEEN|\sLIKE|\sIN\s*\(|\s)/i',
            trim($sql)
        );
    }

    /**
     * 获取语句中的操作符
     *
     * @param string $sql SQL 语句
     *
     * @return string
     */
    public function getOperator(string $sql)
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
            '\s+NOT LIKE',      // NOT LIKE 'expr'
        ];

        $matches = [];

        return preg_match('/' . implode('|', $_operators) . '/i', $sql, $matches) ? trim($matches[0]) : false;
    }

    /**
     * 抛出数据库异常
     *
     * @param DatabaseException $ex Exception
     *
     * @throws DatabaseException
     */
    public function halt(DatabaseException $ex): void
    {
        $errortext = preg_replace('/[\r\n]/', ' ', $this->sql);

        $this->sql = '';

        // 记录到日志
        $dberr = $this->generateErrorData($ex->getMessage(), $ex->getCode());
        $dberr['SQL'] = $errortext;
        NeoLog::error('db', 'InvalidSQL', $dberr);

        // 抛出
        $ex->setMore($dberr);

        throw $ex;
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
        $req = neo()->getRequest();

        return [
            'ex_error' => $error,
            'ex_errno' => $errno,
            'time' => formatLongDate(),
            'script' => html_entity_decode($req->getRequestUri()),
            'referer' => $req->referer(),
            'ip' => $req->getClientIp(),
        ];
    }

    /**
     * 设置是否从主数据库获取数据
     *
     * @param bool $fromMaster
     */
    public function setFromMaster(bool $fromMaster = false): void
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
     * 使用hint强制走主库
     *
     * @param string $sql  SQL 语句
     * @param string $hint Hint
     *
     * @return string
     */
    public function forceMaster(string $sql, string $hint = '/*FORCE_MASTER*/')
    {
        if ($this->getFromMaster() && $sql[0] != '/') {
            $sql = $hint . ' ' . $sql;
        }

        return $sql;
    }
}
