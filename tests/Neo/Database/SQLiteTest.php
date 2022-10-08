<?php

/**
 * @backupGlobals disabled
 *
 * @internal
 * @coversNothing
 */
class SQLiteTest extends BaseTester
{
    /**
     * @var \Neo\Database\SQLite
     */
    public $db;

    public $driver = 'sqlite';

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testSQL()
    {
        $qb = $this->db->queryBuilder();

        $qb->select('*')
            ->from('Chat_015ad63bfa51b4eb9f8f7d4f4507a051')
            ->setFirstResult(0)
            ->setMaxResults(1);

        $this->db->setBinds($qb->getParameters(), $qb->getParameterTypes());
        
        $result = $this->db->explain($qb->getSQL());
        x($result);

        $user = ['mesSvrID' => $result['mesSvrID'], 'msgContent' => $result['msgContent']];

        $this->assertEquals($user, ['mesSvrID' => 6279052186155696195, 'msgContent' => '冯哥']);
    }

    public function testShowCreateTable()
    {
        $table = $this->db->showCreateTable('test');
        $table = preg_replace(["/[\r|\n]/im", "/\s+/"], ['',' '], trim($table));
        $ctsql = 'CREATE TABLE "test" ( "id" INTEGER UNIQUE, "userid" INTEGER NOT NULL DEFAULT 0, "username" TEXT, PRIMARY KEY("id" AUTOINCREMENT))';

        $this->assertEquals($table, $ctsql);

        $table = $this->db->describe('test');
        x($table);
    }

    public function testDatabasePlatform()
    {
        $platform = $this->db->getConnection()->getDatabasePlatform()->getName();
        $this->assertEquals($platform, 'sqlite');
    }


    public function testTruncate()
    {
        //$sql = $this->db->truncate('logcontent');
        //$this->assertEquals($sql, 'DELETE FROM logcontent');
    }
}
