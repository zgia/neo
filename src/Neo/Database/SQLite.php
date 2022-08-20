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
     * @param string $table Table name
     *
     * @return array Fields in table. Keys are name and values are type
     */
    public function describe(string $table)
    {
        return $this->fetchAll('pragma table_info(' . $this->quote($this->stripTags($table)) . ')');
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
        return $this->fetchRow('EXPLAIN QUERY PLAN ' . $sql);
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
        return $this->fetchOne('SELECT sql FROM sqlite_schema WHERE name = ' . $this->quote($this->stripTags($table)));
    }
}
