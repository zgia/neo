<?php

namespace Neo\Database;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;

/**
 * 主从连接 MasterSlaveConnection
 */
class MSConnection extends PrimaryReadReplicaConnection
{
}
