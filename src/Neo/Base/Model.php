<?php

namespace Neo\Base;

/**
 * 模型基类
 */
class Model extends NeoBase
{
    /**
     * 模型的映射表
     *
     * @var string
     */
    protected $table;

    /**
     * 缺省主键，如果主键不是表名+ID，请务必指定主键名称
     *
     * @var string
     */
    protected $tableid;

    /**
     * 删除标记：deleted, deleted_at...
     *
     * @var string
     */
    protected $deletedFlag = 'deleted';

    /**
     * 删除后写入的值：time，其他值
     * 其他值：1，传入什么，写什么
     * time：time()，当前时间
     *
     * @var string
     */
    protected $deletedVal = 1;

    /**
     * 映射表中是否有主键
     *
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
    }

    /**
     * 在主数据库上开启事务
     */
    public function beginTransaction()
    {
        $this->neo->getDB()->beginTransaction();
    }

    /**
     * 在主数据库上提交事务
     */
    public function commit()
    {
        $this->neo->getDB()->commit();
    }

    /**
     * 在主数据库上回滚事务
     */
    public function rollBack()
    {
        $this->neo->getDB()->rollBack();
    }

    /**
     * 设置当前操作的表
     *
     * @param string $table
     */
    public function setTable(string $table)
    {
        $this->table = $table;
    }

    /**
     * 设置当前操作的表的主键名称
     *
     * @param string $tableid
     */
    public function setTableid(string $tableid)
    {
        $this->tableid = $tableid;
    }

    /**
     * 设置删除标记名称
     *
     * @param string $flag
     */
    public function setDeletedFlag(string $flag)
    {
        $this->deletedFlag = $flag;
    }

    /**
     * 设置删除标记的值类型
     *
     * @param string $type
     */
    public function setDeletedVal(string $type)
    {
        $this->deletedVal = $type;
    }

    /**
     * 设置是否有主键
     * @param bool $yesorno
     */
    public function setHasPrimaryKey(bool $yesorno)
    {
        $this->hasPrimaryKey = $yesorno;
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
        if (! empty($conditions['sql'])) {
            return $conditions['sql'];
        }

        $ret['k'] || $ret['k'] = $this->tableid;
        $more['from'] || $more['from'] = "{$this->table} AS {$this->table}";

        return $this->neo->getDB()->selectSQL($conditions, $more, $ret);
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
    public function rows(array $conditions = [], array $more = [], array $ret = [])
    {
        $sql = $this->selectSQL($conditions, $more, $ret);

        // k存在，且不为空，则返回kv数组，key为$ret['k']
        // k存在，且为null，则返回无k数组
        // k不存在，则返回kv数组，key为tableid
        $key = $ret['k'] ?: (array_key_exists('k', $ret) ? null : $this->tableid);
        $element = $ret['e'] ?: null;

        return $this->neo->getDB()->fetchArray($sql, $element, $key);
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
    public function row(array $conditions = [], array $more = [], $returnField = null)
    {
        if (! $more || ! $more['field']) {
            $field = is_array($returnField) ? implode(',', $returnField) : $returnField;
            $field = $field ?: '*';
            $more['field'] = $field;
        }

        $more['limit'] = 1;

        $sql = $this->selectSQL($conditions, $more);

        $row = $this->neo->getDB()->fetchRow($sql);

        if (is_string($returnField) && $returnField) {
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
    public function getRow(int $id)
    {
        return $this->row([$this->tableid => $id]);
    }

    /**
     * 获取一个值
     *
     * @param array $conditions 条件
     * @param array $more       字句
     *
     * @return string
     */
    public function one(array $conditions = [], array $more = [])
    {
        $more['limit'] = 1;

        $sql = $this->selectSQL($conditions, $more);

        return $this->neo->getDB()->fetchOne($sql);
    }

    /**
     * 获取自增表的最新一条数据
     *
     * @return array
     */
    public function latest()
    {
        return $this->row([], ['orderby' => "{$this->tableid} DESC"]);
    }

    /**
     * 获取表某个字段的最大值
     *
     * @param string $field
     *
     * @return string
     */
    public function max(string $field = null)
    {
        $field || $field = $this->tableid;

        if ($field) {
            return (string) $this->one([], ['field' => "MAX({$field})"]);
        }

        return null;
    }

    /**
     * 聚合
     *
     * @param string $field      聚合的元素
     * @param array  $conditions 条件
     * @param string $groupby    分组的元素
     *
     * @return int
     */
    public function sum(string $field, array $conditions = [], string $groupby = '')
    {
        $more = ['field' => "SUM({$field})"];
        if ($groupby) {
            $more['groupby'] = $groupby;
        }

        $total = $this->one($conditions, $more);

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
    public function total(array $conditions = [], ?string $field = '*')
    {
        $field || $field = '*';
        $total = $this->one($conditions, ['field' => "COUNT({$field})"]);

        return (int) $total;
    }

    /**
     * 保存数据
     *
     * @param array $data       数据
     * @param array $conditions 条件
     * @param bool  $replace    当前数据是插入还是替换
     *
     * @return bool|int
     */
    public function save(array $data, array $conditions = [], bool $replace = false)
    {
        if (empty($data)) {
            return false;
        }

        if (isset($data[$this->tableid]) && empty($data[$this->tableid])) {
            unset($data[$this->tableid]);
        }

        if (! isset($data[$this->tableid]) && empty($conditions)) {
            return $this->insert($data, $replace);
        }
        return $this->update($data, $conditions);
    }

    /**
     * 添加新记录
     *
     * @param array  $data    数据
     * @param bool   $replace 当前数据是插入还是替换
     * @param string $table   指定数据库表
     *
     * @return int 最后插入的数据的自增ID或者SQL语句产生数据的行数
     */
    public function insert(array $data, bool $replace = false, ?string $table = null)
    {
        $affected = $this->neo->getDB()->insert($table ?: $this->table, $data, $replace);

        return $this->hasPrimaryKey ? $this->neo->getDB()->insertId() : $this->renewAffectedRows($affected);
    }

    /**
     * 更新数据
     *
     * @param array  $data       数据
     * @param array  $conditions 条件
     * @param string $table      指定数据库表
     *
     * @return int
     */
    public function update(array $data, array $conditions = [], ?string $table = null)
    {
        $affected = $this->neo->getDB()->update($table ?: $this->table, $data, $conditions);

        return $this->renewAffectedRows($affected);
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
            $val = $this->deletedVal == 'time' ? time() : $this->deletedVal;

            $affected = $this->update([$this->deletedFlag => $val], $conditions);
        } else {
            $affected = $this->neo->getDB()->delete($this->table, $conditions);
        }

        return $this->renewAffectedRows($affected);
    }

    /**
     * 按照id删除记录
     *
     * @param mixed $id     id
     * @param bool  $isflag 是否标记删除，默认为标记
     *
     * @return int
     */
    public function deleteItem($id, bool $isflag = true)
    {
        return $this->delete([$this->tableid => $id], $isflag);
    }

    /**
     * 这么处理，通常0表示错误或者失败
     *
     * 大于0的整数：影响行数
     * 0：根据条件没有更新到数据，或者语句没有执行
     * -1：出错了
     *
     * @param int $affected
     *
     * @return int
     */
    protected function renewAffectedRows(int $affected)
    {
        if ($affected == 0) {
            $affected = PHP_INT_MAX;
        } elseif ($affected == -1) {
            $affected = 0;
        }

        return $affected;
    }

    /**
     * 获取错误号
     *
     * @return int
     */
    public function getErrno()
    {
        return $this->neo->getDB()->getErrno();
    }

    /**
     * 获取错误信息
     *
     * @return string
     */
    public function getError()
    {
        return $this->neo->getDB()->getError();
    }
}
