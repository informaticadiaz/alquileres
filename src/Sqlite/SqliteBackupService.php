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

    private const SCHEDULED_PREFIX = 'fewohbee-';
    private const SCHEDULED_SUFFIX = '.sqlite';

    /**
     * Writes a timestamped backup into $directory and, once it is verified, removes the
     * oldest scheduled backups beyond $keep. Only files following the backup naming
     * scheme are ever removed.
     *
     * @return string path of the new backup
     *
     * @throws \RuntimeException when the backup cannot be written or fails verification
     */
    public function backupToDirectory(string $directory, int $keep): string
    {
        if ($keep < 1) {
            throw new \RuntimeException('At least one backup must be kept.');
        }

        $now = new \DateTimeImmutable();
        $target = sprintf('%s/%s%s%s', rtrim($directory, '/'), self::SCHEDULED_PREFIX, $now->format('Ymd-His-u'), self::SCHEDULED_SUFFIX);
        $this->backup($target);

        $existing = glob(sprintf('%s/%s*%s', rtrim($directory, '/'), self::SCHEDULED_PREFIX, self::SCHEDULED_SUFFIX)) ?: [];
        sort($existing);
        foreach (\array_slice($existing, 0, max(0, \count($existing) - $keep)) as $outdated) {
            unlink($outdated);
        }

        return $target;
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
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
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

        // Backups contain guest data: readable by the service user only.
        chmod($target, 0600);
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
