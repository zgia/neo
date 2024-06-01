<?php

namespace Neo\Database;

use Doctrine\DBAL\Logging\DebugStack;
use Neo\NeoLog;

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
    public function stopQuery()
    {
        if (! $this->enabled) {
            return;
        }

        parent::stopQuery();

        NeoLog::info('db', 'query', $this->queries[$this->currentQuery]);
    }
}
