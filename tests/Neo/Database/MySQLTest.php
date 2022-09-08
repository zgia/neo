<?php

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

        $this->db->update('user',['username'=>'zgia', 'openid'=>'cde',0=>'dateline=dateline+1'], ['userid'=>1]);

        $this->db->clearBinds();
        $params = [ 1];
        foreach($params as $param){
            $this->db->bindValue($param);
        }
        $result = $this->db->fetchRow("select * from user where userid = ?");

        print_r($result);

        $user = ['userid' => $result['userid'], 'username' => $result['username']];

        $this->assertEquals($user, ['userid' => 1, 'username' => 'zgia']);
    }

    public function testShowCreateTable()
    {
        $table = $this->db->showCreateTable('user');
        $this->assertEquals(md5($table), '5e9e33f35adfd56f06766c2002e2309b');
    }
}
