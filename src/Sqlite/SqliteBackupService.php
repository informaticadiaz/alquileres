<?php

declare(strict_types=1);

namespace App\Sqlite;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Creates consistent single-file backups of the SQLite database.
 *
 * VACUUM INTO writes the database as seen by one read transaction: every committed
 * change is included, even when it still lives in the WAL, and concurrent uncommitted
 * writes are not. The application can keep running. Copying the database, WAL and SHM
 * files by hand is not safe while writes happen. Every backup is verified before it is
 * reported as usable.
 */
final class SqliteBackupService
{
    public function __construct(private readonly ManagerRegistry $registry)
    {
    }

    /**
     * @throws \RuntimeException when the target exists, cannot be written or fails verification
     */
    public function backup(string $target): void
    {
        $connection = $this->registry->getConnection();
        if (!$connection instanceof Connection || !$connection->getDatabasePlatform() instanceof SQLitePlatform) {
            throw new \RuntimeException('Backups are only supported for the SQLite profile.');
        }

        if (file_exists($target)) {
            throw new \RuntimeException(sprintf('Backup target "%s" already exists; refusing to overwrite it.', $target));
        }

        $directory = \dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Could not create backup directory "%s".', $directory));
        }

        try {
            $connection->executeStatement('VACUUM INTO ?', [$target]);
        } catch (DbalException $exception) {
            throw new \RuntimeException(sprintf('Could not write backup "%s": %s', $target, $exception->getMessage()), 0, $exception);
        }

        try {
            $this->verify($target);
        } catch (\Throwable $exception) {
            if (is_file($target)) {
                unlink($target);
            }

            throw new \RuntimeException(sprintf('Backup "%s" failed verification and was removed: %s', $target, $exception->getMessage()), 0, $exception);
        }
    }

    private function verify(string $path): void
    {
        $backup = new \PDO('sqlite:'.$path, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $integrity = $backup->query('PRAGMA integrity_check')->fetchAll(\PDO::FETCH_COLUMN);
        if (['ok'] !== $integrity) {
            throw new \RuntimeException('integrity_check reported: '.implode('; ', $integrity));
        }

        if ([] !== $backup->query('PRAGMA foreign_key_check')->fetchAll()) {
            throw new \RuntimeException('foreign_key_check reported violations.');
        }
    }
}
