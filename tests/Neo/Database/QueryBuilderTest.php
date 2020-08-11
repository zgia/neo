<?php

use Neo\Database\MySQLi;
use Neo\Database\Query\QueryBuilder;
use Neo\Neo;

/**
 * @backupGlobals disabled
 *
 * @internal
 * @coversNothing
 */
class QueryBuilderTest extends BaseTester
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testFrom()
    {
        $qb = $this->qb;

        $qb->from('user', 'u');

        $this->assertEquals($qb->getQueryPart('from')[0], [
            'table' => 'user',
            'alias' => 'u',
        ]);
        //$this->assertEquals(600, $w);
        //$this->assertEquals(800, $h);
    }

    public function testSQL()
    {
        $username = 'zgia';
        $password = '123456';
        $email = 'zgia@163.com';

        $qb = $this->qb;

        $qb->insert('users')
            ->setValue('name', $username)
            ->setValue('password', $password);

        $sql = "INSERT INTO users (name, password) VALUES(zgia, 123456)";
        $this->assertEquals($qb->getSQL(), $sql);

        $qb->resetQueryParts();
        $qb->select('id', 'name')
            ->from('users')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->eq('username', $username),
                    $qb->expr()->eq('email', $email)
                )
            );
        $sql = "SELECT id, name FROM users WHERE (username = zgia) AND (email = zgia@163.com)";
        $this->assertEquals($qb->getSQL(), $sql);

        $qb->resetQueryParts();
        $qb->update('users', 'u')
            ->set('u.logins', 'u.logins + 1')
            ->set('u.last_login', 's132423543');
        $sql = "UPDATE users u SET u.logins = u.logins + 1, u.last_login = s132423543";
        $this->assertEquals($qb->getSQL(), $sql);

        $qb->resetQueryParts();
        $qb->select('username')
            ->from('User', 'u')
            ->where($qb->expr()->orX(
                $qb->expr()->eq('u.id', '?1'),
                $qb->expr()->like('u.nickname', '?2')
            ))
            ->orderBy('u.surname', 'ASC');

        $sql = "SELECT username FROM User u WHERE (u.id = ?1) OR (u.nickname LIKE ?2) ORDER BY u.surname ASC";
        $this->assertEquals($qb->getSQL(), $sql);
    }
}
