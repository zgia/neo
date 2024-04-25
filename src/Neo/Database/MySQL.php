<?php

namespace Neo\Database;

/**
 * MySQL
 */
class MySQL extends PDO
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
        return $this->fetchAll('DESCRIBE ' . $this->stripTags($table));
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
        return $this->fetchAll('EXPLAIN ' . $sql);
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
        $data = $this->fetchAll('SHOW CREATE TABLE ' . $this->stripTags($table));

        return $data[0]['Create Table'] ?? '';
    }
}
