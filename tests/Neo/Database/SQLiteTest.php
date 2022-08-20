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
        $table = $this->db->describe('Chat_015ad63bfa51b4eb9f8f7d4f4507a051');

        //$this->assertEquals(md5($table), '5e9e33f35adfd56f06766c2002e2309b');

    }
}
