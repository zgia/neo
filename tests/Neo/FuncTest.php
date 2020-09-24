<?php

use Neo\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * @backupGlobals disabled
 *
 * @internal
 * @coversNothing
 */
class FuncTest extends BaseTester
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testDump()
    {
        $req = neo()->getRequest();
        $req->setAjax(true);

        dump('dasdas');
        dump('yrtrew');
        x(423,342423);

        $this->assertEquals(get_class($req),'Neo\Http\Request');
        $this->assertEquals(neo()->getRequest()->isAjax(), true);
    }

}
