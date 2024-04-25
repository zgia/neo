<?php

use Doctrine\DBAL\Schema\Table;

/**
 * @backupGlobals disabled
 *
 * @internal
 * @coversNothing
 */
class MySQLTest extends BaseTester
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testSQL()
    {
        /*
        $qb = $this->db->queryBuilder();

        $qb->select('*')
            ->from('user')
            ->setFirstResult(0)
            ->setMaxResults(1);

        $this->db->setBinds($qb->getParameters(), $qb->getParameterTypes());
        
        $result = $this->db->fetchRow($qb->getSQL());
        // */

        $this->db->update('user',['username'=>'zgia', 'openid'=>'cde',0=>'dateline=dateline+1'], ['id'=>1]);

        $this->db->clearBinds();
        $params = [ 1];
        foreach($params as $param){
            $this->db->bindValue($param);
        }
        $result = $this->db->fetchRow("select * from user where id = ?");

        print_r($result);

        $user = ['id' => $result['id'], 'username' => $result['username']];

        $this->assertEquals($user, ['id' => 1, 'username' => 'zgia']);
    }

    public function testShowCreateTable()
    {
        $table = $this->db->showCreateTable('user');
        $this->assertEquals(md5($table), 'ac819157901985c4baa327dbdd1aa52c');
    }

    public function testDatabasePlatform()
    {
        $platform = $this->db->getConnection()->getDatabasePlatform()->getName();
        $this->assertEquals($platform, 'mysql');
    }

    public function testCreateTable()
    {
        $sql = $this->db->getConnection()->getDatabasePlatform()->getCreateTableSQL(new Table('log'));
        x($sql);
        //$this->assertEquals($sql, 'TRUNCATE logcontent');
    }
}
