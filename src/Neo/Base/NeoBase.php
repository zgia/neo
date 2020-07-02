<?php

namespace Neo\Base;

use Neo\NeoFrame;

/**
 * Class NeoBase
 */
class NeoBase
{
    /**
     * @var NeoFrame
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
