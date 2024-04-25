<?php

use Neo\Traiter\GuzzleHttpClientTraiter;

/**
 * @backupGlobals disabled
 *
 * @internal
 * @coversNothing
 */
class RequestTest extends BaseTester
{
    use GuzzleHttpClientTraiter;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testCreateRequest()
    {
        $req = neo()->getRequest();

        $this->assertEquals(get_class($req), 'Neo\Http\Request');
        $this->assertEquals(get_parent_class($req), 'Symfony\Component\HttpFoundation\Request');
    }
}
