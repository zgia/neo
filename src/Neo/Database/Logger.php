<?php

namespace Neo\Database;

use Doctrine\DBAL\Logging\DebugStack;

/**
 * Logger Class
 *
 * Class Logger
 */
class Logger extends DebugStack
{
    /**
     * 查询记录
     *
     * @return array
     */
    public function getQueries()
    {
        return $this->queries;
    }

    /**
     * 禁用日志
     */
    public function disable()
    {
        $this->enabled = false;
    }
}
