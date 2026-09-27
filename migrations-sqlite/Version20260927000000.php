<?php

declare(strict_types=1);

namespace SqliteMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Start of the SQLite migration line.
 *
 * Every SQLite database is created by App\Sqlite\SqliteBaselineInitializer, which marks
 * all migrations of this line as executed. Reaching this migration therefore means the
 * database was not created by the baseline, and the line must not be applied to it.
 */
final class Version20260927000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'SQLite line start: require a database created by the sqlite-schema-tool-v1 baseline.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof SQLitePlatform, 'The SQLite migration line only runs on SQLite.');
        $baselines = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'fewohbee_sqlite_baseline_metadata'"
        );
        $this->abortIf(0 === $baselines, 'This database was not created by the SQLite baseline; run App\Sqlite\SqliteBaselineInitializer on a fresh database.');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The start of the SQLite line cannot be reverted.');
    }
}
