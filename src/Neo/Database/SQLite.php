<?php

namespace Neo\Database;

/**
 * SQLite
 */
class SQLite extends PDO
{
    /**
     * MySQL destructor
     */
    public function __destruct()
    {
        $this->close();
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
        return $this->fetchAll('pragma table_info(' . $this->quote($this->stripTags($table)) . ')');
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
        return $this->fetchRow('EXPLAIN QUERY PLAN ' . $sql);
    }

    /**
     * 返回创建表的语句
     *
     * @param string $table 表名
     *
     * @return string 创建表的语句
     */
    public function showCreateTable(string $table)
    {
        $this->clearBinds();
        $this->bindValue($this->stripTags($table));

        return $this->fetchOne('SELECT sql FROM sqlite_schema WHERE name = ?');
    }
}
