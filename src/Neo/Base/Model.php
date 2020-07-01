<?php

namespace Neo\Base;

/**
 * 模型基类
 */
class Model extends NeoBase
{
    /**
     * 模型的映射表
     * @var string
     */
    protected $table;

    /**
     * 缺省主键，如果主键不是表名+ID，请务必指定主键名称
     * @var string
     */
    protected $tableid;

    // 下面2个参数用于在模型中操作另一张表的处理
    protected $oldTable;

    protected $oldTableid;

    /**
     * 映射表中是否有主键
     * @var bool
     */
    protected $hasPrimaryKey = true;

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();

        if (! $this->table) {
            $this->table = str_replace('model', '', strtolower(get_class($this)));
        }

        // 缺省主键，如果主键不是表名+ID，请务必指定主键名称
        if (! $this->tableid) {
            $this->tableid = $this->table . 'id';
        }

        $this->userid = $this->neo->userid;
    }

    /**
     * 在主数据库上开启事务
     */
    public function beginTransaction()
    {
        $this->getDB()
            ->beginTransaction();
    }

    /**
     * 在主数据库上提交事务
     */
    public function commit()
    {
        $this->getDB()
            ->commit();
    }

    /**
     * 在主数据库上回滚事务
     */
    public function rollback()
    {
        $this->getDB()
            ->rollback();
    }

    /**
     * 获取当前操作的表
     *
     * @return mixed|string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * 当需要在模型中使用Model基类中的方法操作另一个表时，应该使用这个方法。
     * 如果另一张表的主键不是：表名+ID，那么这里必须传入tableid。
     * 否则将无法获取正确的结果。
     *
     * 注：如果不想传入主键，则需要将第二个参数设置为NULL，即：setTable($tbl, NULL);
     *
     * @param string $table   表名
     * @param string $tableid 表的主键
     */
    public function setTable(string $table, string $tableid = 'nil')
    {
        $this->oldTable = $this->table;
        $this->oldTableid = $this->tableid;

        $this->table = $table;
        if ($tableid === 'nil') {
            $this->tableid = $this->table . 'id';
        } elseif ($tableid) {
            $this->tableid = $tableid;
        } else {
            $this->tableid = null;
        }
    }

    /**
     * 当在模型中操作另一张表时，查询后，一定要恢复为当前模型的缺省表
     *
     * @see setTable($table, $tableid = NULL)
     */
    public function restoreTable()
    {
        if (! isset($this->oldTable) || ! $this->oldTable) {
            return;
        }
        $this->table = $this->oldTable;
        $this->tableid = $this->oldTableid;
        unset($this->oldTable, $this->oldTableid);
    }

    /**
     * 根据条件生成SQL
     *
     * @param array $conditions 条件
     * @param array $more       ORDER BY, LIMIT, GROUP BY 等等
     * @param array $ret        指定返回的数组元素
     *
     * @return string
     */
    public function selectSQL(array $conditions = [], array $more = [], array $ret = [])
    {
        $ret['k'] || $ret['k'] = $this->tableid;
        $more['from'] || $more['from'] = "{$this->table} AS {$this->table}";

        return $this->getDB()
            ->selectSQL($conditions, $more, $ret);
    }

    /**
     * 根据条件生成SQL
     *
     * @param array $conditions 条件
     * @param array $more       ORDER BY, LIMIT, GROUP BY 等等
     * @param array $ret        指定返回的数组元素
     *
     * @return array
     */
    public function items(array $conditions = [], array $more = [], array $ret = [])
    {
        $sql = $this->selectSQL($conditions, $more, $ret);

        // k存在，且不为空，则返回kv数组，key为$ret['k']
        // k存在，且为null，则返回无k数组
        // k不存在，则返回kv数组，key为tableid
        $key = $ret['k'] ?: (array_key_exists('k', $ret) ? null : $this->tableid);
        $element = $ret['e'] ?: null;

        if (array_key_exists('obj', $ret)) {
            return $this->getDB()
                ->fetchObjectArray($sql, $ret['obj']);
        }

        return $this->getDB()
            ->fetchArray($sql, $element, $key);
    }

    /**
     * 获取单条信息
     *
     * @param array        $conditions  条件
     * @param array        $more        字句
     * @param array|string $returnField 指定返回的数组元素
     *
     * @return null|array|object
     */
    public function item(array $conditions = [], array $more = [], $returnField = null)
    {
        if (! $more || ! $more['field']) {
            $field = is_array($returnField) ? implode(',', $returnField) : $returnField;
            $field = $field ?: '*';
            $more['field'] = $field;
        }

        $more['limit'] = 1;

        $sql = $this->selectSQL($conditions, $more);

        if (array_key_exists('obj', $more)) {
            $row = $this->getDB()
                ->fetchObjectFirst($sql, $more['obj']);

            if (is_string($returnField) && strlen($returnField) > 0) {
                return $row ? $row->{$returnField} : null;
            }

            return $row;
        }

        $row = $this->getDB()
            ->first($sql);

        if (is_string($returnField)) {
            return $row ? $row[$returnField] : null;
        }

        return (array) $row;
    }

    /**
     * 根据主键获取一项
     *
     * @param int $id
     *
     * @return array
     */
    public function getItem(int $id)
    {
        return $this->item([$this->tableid => $id]);
    }

    /**
     * 获取自增表的最新一条数据
     *
     * @return array
     */
    public function latest()
    {
        return $this->item([], ['orderby' => "{$this->tableid} DESC"]);
    }

    /**
     * 获取一个值
     *
     * @param array $conditions 条件
     * @param array $more       字句
     *
     * @return string
     */
    public function single(array $conditions = [], array $more = [])
    {
        $more['limit'] = 1;

        $sql = $this->selectSQL($conditions, $more);

        return $this->getDB()
            ->single($sql);
    }

    /**
     * 获取表某个字段的最大值
     *
     * @param string $field
     *
     * @return int
     */
    public function maxId(string $field = null)
    {
        $field || $field = $this->tableid;
        $max = $this->single([], ['field' => "MAX({$field})"]);

        return (int) $max;
    }

    /**
     * 聚合
     *
     * @param string $ele        聚合的元素
     * @param array  $conditions 条件
     * @param string $groupby    分组的元素
     *
     * @return int
     */
    public function sum(string $ele, array $conditions = [], string $groupby = '')
    {
        $more = ['field' => "SUM({$ele})"];
        if ($groupby) {
            $more['groupby'] = $groupby;
        }

        $total = $this->single($conditions, $more);

        return (int) $total;
    }

    /**
     * 获取符合条件的数目
     *
     * @param array  $conditions 条件
     * @param string $field      统计字段
     *
     * @return int
     */
    public function total(array $conditions = [], string $field = '*')
    {
        $total = $this->single(
            $conditions,
            [
                'field' => "COUNT({$field})",
            ]
        );

        return (int) $total;
    }

    /**
     * 保存数据
     *
     * @param array $data       数据
     * @param array $conditions 条件
     * @param bool  $replace    当前数据是插入还是替换
     *
     * @return mixed int or false
     */
    public function save(array $data, array $conditions = [], bool $replace = false)
    {
        if (empty($data)) {
            return false;
        }

        if (isset($data[$this->tableid]) && empty($data[$this->tableid])) {
            unset($data[$this->tableid]);
        }

        return ! isset($data[$this->tableid]) && empty($conditions) ? $this->newItem(
            $data,
            $replace
        ) : $this->updateItem(
            $data,
            $conditions
        );
    }

    /**
     * 添加新记录
     *
     * @param array $data    数据
     * @param bool  $replace 当前数据是插入还是替换
     *
     * @return int 最后插入的数据的自增ID或者SQL语句产生数据的行数
     */
    public function newItem(array $data, bool $replace = false)
    {
        if (! $replace) {
            unset($data[$this->tableid]);
        }

        $affected = $this->getDB()
            ->insert($this->table, $data, $replace);

        return $this->hasPrimaryKey ? $this->getDB()
            ->insertId() : $affected;
    }

    /**
     * 添加新记录
     *
     * @param array  $data       数据
     * @param array  $conditions 条件
     * @param string $table      数据库表
     *
     * @return int
     */
    public function updateItem(array $data, array $conditions = [], string $table = '')
    {
        if ($data[$this->tableid]) {
            $id = $data[$this->tableid];
            unset($data[$this->tableid]);

            if (! array_key_exists($this->tableid, $conditions)) {
                $conditions[$this->tableid] = $id;
            }
        }

        return $this->update($data, $conditions, $table);
    }

    /**
     * 更新数据
     *
     * @param array  $data
     * @param array  $conditions
     * @param string $table
     *
     * @return int
     */
    public function update(array $data, array $conditions = [], string $table = '')
    {
        $table || $table = $this->table;

        $affected = $this->getDB()
            ->update($table, $data, $conditions);

        $this->renewAffectedRows($affected);

        return $affected;
    }

    /**
     * 删除记录
     *
     * @param array $conditions 条件
     * @param bool  $isflag     是否标记删除，默认为标记
     *
     * @return int
     */
    public function delete(array $conditions, bool $isflag = true)
    {
        // 不允许不带条件的删除
        if (empty($conditions)) {
            return false;
        }

        // 是否标记为删除
        if ($isflag) {
            $affected = $this->updateItem(['deleted' => 1], $conditions);
        } else {
            $affected = $this->getDB()
                ->delete($this->table, $conditions);
        }

        $this->renewAffectedRows($affected);

        return $affected;
    }

    /**
     * 按照id删除记录
     *
     * @param int  $id     id
     * @param bool $isflag 是否标记删除，默认为标记
     *
     * @return int
     */
    public function deleteItem(int $id, bool $isflag = true)
    {
        return $this->delete([$this->tableid => $id], $isflag);
    }

    /**
     * 批量添加新纪录
     *
     * @param array $data    数据
     * @param bool  $replace 当前数据是插入还是替换
     * @param int   $limit   分批写入数量
     *
     * @return mixed SQL语句产生数据的行数
     */
    public function newBatchItem(array $data, bool $replace = false, int $limit = 200)
    {
        $affected = $this->getDB()
            ->batchInsert($this->table, $data, $replace, $limit);
        $this->renewAffectedRows($affected);

        return $affected;
    }

    /**
     * 这么处理，通常0表示错误或者失败
     *
     * 大于0的整数：影响行数
     * 0：根据条件没有更新到数据，或者语句没有执行
     * -1：出错了
     *
     * @param int $affected
     */
    protected function renewAffectedRows(int &$affected)
    {
        if ($affected == 0) {
            $affected = PHP_INT_MAX;
        } elseif ($affected == -1) {
            $affected = 0;
        }
    }

    /**
     * 获取错误号
     *
     * @return int
     */
    public function getErrno()
    {
        return $this->getDB()
            ->getErrno();
    }

    /**
     * 获取错误信息
     *
     * @return string
     */
    public function getError()
    {
        return $this->getDB()
            ->getError();
    }
}
