<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Sqlite\SqliteBaselineInitializer;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SqliteBaselineInitializerTest extends KernelTestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();

        $fixtureDirectory = dirname(__DIR__, 2).'/var/sqlite-test';
        if (!is_dir($fixtureDirectory)) {
            mkdir($fixtureDirectory, 0770, true);
        }

        $this->fixturePath = $fixtureDirectory.'/fewohbee.sqlite';
        $this->removeFixture();
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        $this->removeFixture();

        parent::tearDown();
    }

    public function testCreatesTheFirstRunSchemaAndRecordsItsOwnBaselineWithoutUpstreamMigrations(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);

        /** @var SqliteBaselineInitializer $initializer */
        $initializer = self::getContainer()->get(SqliteBaselineInitializer::class);
        $initializer->initialize();

        /** @var ManagerRegistry $registry */
        $registry = self::getContainer()->get(ManagerRegistry::class);
        $default = $registry->getConnection('default');
        $geo = $registry->getConnection('geo');
        $tables = $default->fetchFirstColumn("SELECT name FROM sqlite_master WHERE type = 'table'");

        self::assertContains('fewohbee_sqlite_baseline_metadata', $tables);
        self::assertContains('users', $tables);
        self::assertContains('roles', $tables);
        self::assertContains('template_types', $tables);
        self::assertContains('templates', $tables);
        self::assertContains('objects', $tables);
        self::assertContains('customers', $tables);
        self::assertContains('guest_categories', $tables);
        self::assertSame(1, (int) $default->fetchOne("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'postal_code_data'"));
        self::assertNotContains('doctrine_migration_versions', $tables);
        self::assertSame(SqliteBaselineInitializer::VERSION, $default->fetchOne('SELECT baseline_id FROM fewohbee_sqlite_baseline_metadata'));
        self::assertSame(0, (int) $default->fetchOne('SELECT COUNT(*) FROM users'));
        self::assertSame(1, (int) $default->fetchOne('PRAGMA foreign_keys'));
        self::assertSame(1, (int) $geo->fetchOne('PRAGMA foreign_keys'));
        self::assertSame('wal', strtolower((string) $default->fetchOne('PRAGMA journal_mode')));
        self::assertSame('wal', strtolower((string) $geo->fetchOne('PRAGMA journal_mode')));
    }

    private function removeFixture(): void
    {
        foreach ([$this->fixturePath, $this->fixturePath.'-wal', $this->fixturePath.'-shm'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
