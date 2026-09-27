<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use SensitiveParameter;

final class SqliteConnectionPragmaDriver extends AbstractDriverMiddleware
{
    public function connect(#[SensitiveParameter] array $params): Connection
    {
        $connection = parent::connect($params);

        if (($params['driver'] ?? null) !== 'pdo_sqlite') {
            return $connection;
        }

        $connection->exec('PRAGMA foreign_keys = ON');
        $connection->exec('PRAGMA journal_mode = WAL');
        $connection->exec('PRAGMA busy_timeout = 5000');

        return $connection;
    }
}
