<?php

use Neo\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * @backupGlobals disabled
 *
 * @internal
 * @coversNothing
 */
class RequestTest extends BaseTester
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testCreateRequest()
    {
        $req = neo()->getRequest();

        $this->assertEquals(get_class($req),'Neo\Http\Request');
        $this->assertEquals(get_parent_class($req),'Symfony\Component\HttpFoundation\Request');
    }

}
