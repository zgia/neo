<?php

namespace Neo\Exception;

/**
 * Neo 异常
 *
 * Class NeoException
 */
class NeoException extends \RuntimeException
{
    /**
     * @var array
     */
    private $more = [];

    /**
     * @param array $more
     */
    public function setMore($more = [])
    {
        $this->more = $more;
    }

    /**
     * @return array
     */
    public function getMore()
    {
        return $this->more;
    }
}
