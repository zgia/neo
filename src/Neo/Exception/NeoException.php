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
     * @var array<mixed>
     */
    private $more = [];

    /**
     * @param array<mixed> $more
     */
    public function setMore($more = []): void
    {
        $this->more = $more;
    }

    /**
     * @return array<mixed>
     */
    public function getMore()
    {
        return $this->more;
    }
}
