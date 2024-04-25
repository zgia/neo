<?php

namespace Neo\Base;

use Neo\Database\AbstractDatabase;
use Neo\Database\DatabaseInterface;
use Neo\Database\MySQL;

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
    protected string $deletedVal = '1';

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
            $this->tableid = 'id';
        }
    }

    /**
     * DB 实例
     *
     * @return AbstractDatabase|DatabaseInterface|MySQL
     */
    public function db()
    {
        return $this->neo->getDB();
    }

    /**
     * 在主数据库上开启事务
     */
    public function beginTransaction(): void
    {
        $this->db()->beginTransaction();
    }

    /**
     * 在主数据库上提交事务
     */
    public function commit(): void
    {
        $this->db()->commit();
    }

    /**
     * 在主数据库上回滚事务
     */
    public function rollBack(): void
    {
        $this->db()->rollBack();
    }

    /**
     * 设置当前操作的表
     *
     * @param string $table
     */
    public function setTable(string $table): void
    {
        $this->table = $table;
    }

    /**
     * 设置当前操作的表的主键名称
     *
     * @param string $tableid
     */
    public function setTableid(string $tableid): void
    {
        $this->tableid = $tableid;
    }

    /**
     * 设置删除标记名称
     *
     * @param string $flag
     */
    public function setDeletedFlag(string $flag): void
    {
        $this->deletedFlag = $flag;
    }

    /**
     * 设置删除标记的值类型
     *
     * @param string $type
     */
    public function setDeletedVal(string $type): void
    {
        $this->deletedVal = $type;
    }

    /**
     * 设置是否有主键
     *
     * @param bool $yesorno
     */
    public function setHasPrimaryKey(bool $yesorno): void
    {
        $this->hasPrimaryKey = $yesorno;
    }

    /**
     * 根据条件生成SQL
     *
     * @param array<string,mixed> $conditions 条件
     * @param array<string,mixed> $more       ORDER BY, LIMIT, GROUP BY 等等
     * @param array<string>       $ret        指定返回的数组元素
     *
     * @return string
     */
    public function selectSQL(array $conditions = [], array $more = [], array $ret = [])
    {
        // 可以直接传入SQL
        if (! empty($conditions['sql'])) {
            return $conditions['sql'];
        }

        if (empty($ret['k'])) {
            $ret['k'] = $this->tableid;
        }

        if (empty($more['from'])) {
            $more['from'] = "{$this->table} AS {$this->table}";
        }

        return $this->db()->selectSQL($conditions, $more, $ret);
    }

    /**
     * 根据条件生成SQL
     *
     * @param array<string,mixed> $conditions 条件
     * @param array<string,mixed> $more       ORDER BY, LIMIT, GROUP BY 等等
     * @param array<string>       $ret        指定返回的数组元素
     *
     * @return array<array<string,mixed>>
     */
    public function rows(array $conditions = [], array $more = [], array $ret = [])
    {
        $sql = $this->selectSQL($conditions, $more, $ret);

        // k存在，且不为空，则返回kv数组，key为$ret['k']
        // k存在，且为null或者为假，则返回无k数组
        // k不存在，则返回kv数组，key为tableid
        $key = (! isset($ret['k'])) ? $this->tableid : ($ret['k'] ?: null);
        $element = empty($ret['e']) ? null : $ret['e'];

        return $this->db()->fetchArray($sql, $element, $key);
    }

    /**
     * 获取单条信息
     *
     * @param array<string,mixed>  $conditions  条件
     * @param array<string,mixed>  $more        字句
     * @param array<string>|string $returnField 指定返回的数组元素
     *
     * @return null|array<string,mixed>|mixed
     */
    public function row(array $conditions = [], array $more = [], $returnField = null)
    {
        if (! $more || empty($more['field'])) {
            $field = is_array($returnField) ? implode(',', $returnField) : $returnField;
            $more['field'] = $field ?: '*';
        }

        $more['limit'] = 1;

        $sql = $this->selectSQL($conditions, $more);

        $row = $this->db()->fetchRow($sql);

        if ($returnField && is_string($returnField)) {
            return $row[$returnField] ?? null;
        }

        return $row ? $row : null;
    }

    /**
     * 根据主键获取一项
     *
     * @param int $id
     *
     * @return array<string,mixed>
     */
    public function getRow(int $id)
    {
        return $this->row([$this->tableid => $id]);
    }

    /**
     * 获取一个值
     *
     * @param array<string,mixed> $conditions 条件
     * @param array<string,mixed> $more       字句
     *
     * @return string
     */
    public function one(array $conditions = [], array $more = [])
    {
        $more['limit'] = 1;

        $sql = $this->selectSQL($conditions, $more);

        return $this->db()->fetchOne($sql);
    }

    /**
     * 获取自增表的最新一条数据
     *
     * @param array<string,mixed> $conditions
     *
     * @return array<string,mixed>
     */
    public function latest(array $conditions = [])
    {
        return $this->row($conditions, ['orderby' => "{$this->tableid} DESC"]);
    }

    /**
     * 获取表某个字段的最大值
     *
     * @param string              $field
     * @param array<string,mixed> $conditions
     *
     * @return null|string
     */
    public function max(string $field = null, array $conditions = [])
    {
        $field || $field = $this->tableid;

        if ($field) {
            return (string) $this->one($conditions, ['field' => "MAX({$field})"]);
        }

        return null;
    }

    /**
     * 聚合
     *
     * @param string              $field      聚合的元素
     * @param array<string,mixed> $conditions 条件
     * @param string              $groupby    分组的元素
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
     * @param array<string,mixed> $conditions 条件
     * @param string              $field      统计字段
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
     * 在主数据库上执行的"写"操作，比如：insert，update，delete等等
     *
     * @param string $sql The text of the SQL query to be executed
     *
     * @return int
     */
    public function write(string $sql)
    {
        return $this->db()->write($sql);
    }

    /**
     * 保存数据
     *
     * @param array<string,mixed> $data       数据
     * @param array<string,mixed> $conditions 条件
     *
     * @return bool|int
     */
    public function save(array $data, array $conditions = [])
    {
        if (empty($data)) {
            return false;
        }

        if (isset($data[$this->tableid]) && empty($data[$this->tableid])) {
            unset($data[$this->tableid]);
        }

        if (! isset($data[$this->tableid]) && empty($conditions)) {
            return $this->insert($data);
        }

        return $this->update($data, $conditions);
    }

    /**
     * 添加新记录
     *
     * @param array<string,mixed> $data  数据
     * @param string              $table 指定数据库表
     *
     * @return int 最后插入的数据的自增ID或者SQL语句产生数据的行数
     */
    public function insert(array $data, ?string $table = null)
    {
        $affected = $this->db()->insert($table ?: $this->table, $data);

        return $this->hasPrimaryKey ? $this->db()->lastInsertId() : $this->renewAffectedRows($affected);
    }

    /**
     * 更新数据
     *
     * @param array<string,mixed> $data       数据
     * @param array<string,mixed> $conditions 条件
     * @param string              $table      指定数据库表
     *
     * @return int
     */
    public function update(array $data, array $conditions = [], ?string $table = null)
    {
        $affected = $this->db()->update($table ?: $this->table, $data, $conditions);

        return $this->renewAffectedRows($affected);
    }

    /**
     * 删除记录
     *
     * @param array<string,mixed> $conditions 条件
     * @param bool                $isflag     是否标记删除，默认为标记
     *
     * @return int
     */
    public function delete(array $conditions, bool $isflag = true)
    {
        // 不允许不带条件的删除
        if (empty($conditions)) {
            return 0;
        }

        // 是否标记为删除
        if ($isflag) {
            $val = $this->deletedVal == 'time' ? time() : $this->deletedVal;

            $affected = $this->update([$this->deletedFlag => $val], $conditions);
        } else {
            $affected = $this->db()->delete($this->table, $conditions);
        }

        return $this->renewAffectedRows($affected);
    }

    /**
     * 按照id删除记录
     *
     * @param int|string $id     id
     * @param bool       $isflag 是否标记删除，默认为标记
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
}
