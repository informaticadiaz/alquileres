<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;

/**
 * Starts every top-level DBAL transaction with BEGIN IMMEDIATE.
 *
 * SQLite's default deferred BEGIN takes the write lock only on the first write. A
 * transaction that reads (e.g. checks availability) and then writes can therefore fail
 * with SQLITE_BUSY without honouring busy_timeout, or act on a snapshot another writer
 * has already invalidated. Reserving the lock up front serializes writers instead.
 * Nested DBAL transactions use savepoints and never reach this class.
 */
final class SqliteImmediateTransactionConnection extends AbstractConnectionMiddleware
{
    public function beginTransaction(): void
    {
        $this->exec('BEGIN IMMEDIATE');
    }

    public function commit(): void
    {
        $this->exec('COMMIT');
    }

    public function rollBack(): void
    {
        $this->exec('ROLLBACK');
    }
}
