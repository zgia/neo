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
     * @return array<int,array<string,mixed>>
     */
    public function getQueries()
    {
        return $this->queries;
    }

    /**
     * 禁用日志
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, ?array $params = null, ?array $types = null)
    {
        if (count($this->queries) > 1000) {
            // todo anything?
        }

        parent::startQuery($sql, $params, $types);
    }
}
