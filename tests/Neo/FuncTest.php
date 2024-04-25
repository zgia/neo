<?php

use Neo\NeoLog;

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
        x(423, 342423);

        $this->assertEquals(get_class($req), 'Neo\Http\Request');
        $this->assertEquals(neo()->getRequest()->isAjax(), true);
    }

    public function testNeoLog()
    {
        NeoLog::error('test', 'this is a log');

        $this->assertEquals(1, true);
    }
}
