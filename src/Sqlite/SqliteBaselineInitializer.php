<?php

declare(strict_types=1);

namespace App\Sqlite;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\ExecutionResult;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Creates the SQLite schema baseline for the market-validation fork.
 *
 * This intentionally does not execute the upstream MySQL migration history.
 */
final class SqliteBaselineInitializer
{
    public const VERSION = 'sqlite-schema-tool-v1';
    private const METADATA_TABLE = 'fewohbee_sqlite_baseline_metadata';

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly ?DependencyFactory $migrations = null,
    ) {
    }

    public function initialize(): void
    {
        $default = $this->entityManager('default');
        $geo = $this->entityManager('geo');
        $connection = $default->getConnection();

        $this->assertSqlite($connection, 'default');
        $this->assertSqlite($geo->getConnection(), 'geo');

        // Queried directly: the profile's schema filter hides its bookkeeping tables.
        $tables = $connection->fetchFirstColumn("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
        if (in_array(self::METADATA_TABLE, $tables, true)) {
            $version = $connection->fetchOne(sprintf('SELECT baseline_id FROM %s LIMIT 1', self::METADATA_TABLE));
            if (self::VERSION !== $version) {
                throw new \LogicException('The SQLite database has an unknown baseline version.');
            }

            return;
        }

        if ([] !== $tables) {
            throw new \LogicException('The SQLite baseline may only initialize a fresh database.');
        }

        (new SchemaTool($default))->createSchema($default->getMetadataFactory()->getAllMetadata());
        (new SchemaTool($geo))->createSchema($geo->getMetadataFactory()->getAllMetadata());

        $connection->executeStatement(
            sprintf('CREATE TABLE %s (baseline_id VARCHAR(64) NOT NULL PRIMARY KEY, initialized_at DATETIME NOT NULL)', self::METADATA_TABLE)
        );
        $connection->executeStatement(
            sprintf('INSERT INTO %s (baseline_id, initialized_at) VALUES (?, CURRENT_TIMESTAMP)', self::METADATA_TABLE),
            [self::VERSION],
        );

        $this->markMigrationLineExecuted();
    }

    /**
     * The schema was just created from the current mappings, which already contain every
     * change of the SQLite migration line; running those migrations again would fail.
     */
    private function markMigrationLineExecuted(): void
    {
        if (null === $this->migrations) {
            return;
        }

        $storage = $this->migrations->getMetadataStorage();
        $storage->ensureInitialized();
        $executed = $storage->getExecutedMigrations();

        foreach ($this->migrations->getMigrationRepository()->getMigrations()->getItems() as $migration) {
            if (!$executed->hasMigration($migration->getVersion())) {
                $storage->complete(new ExecutionResult($migration->getVersion(), Direction::UP, new \DateTimeImmutable()));
            }
        }
    }

    private function entityManager(string $name): EntityManagerInterface
    {
        $manager = $this->registry->getManager($name);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('The "%s" manager must be a Doctrine ORM entity manager.', $name));
        }

        return $manager;
    }

    private function assertSqlite(Connection $connection, string $name): void
    {
        if (!$connection->getDatabasePlatform() instanceof SQLitePlatform) {
            throw new \LogicException(sprintf('The SQLite baseline cannot run on the "%s" connection.', $name));
        }
    }
}
