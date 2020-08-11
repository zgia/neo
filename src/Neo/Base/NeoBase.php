<?php

namespace Neo\Base;

use Neo\Neo;

/**
 * Class NeoBase
 */
class NeoBase
{
    /**
     * @var Neo
     */
    protected $neo;

    /**
     * NeoBase constructor
     */
    public function __construct()
    {
        $this->neo = neo();
    }
}
