<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Sqlite\SqliteBaselineInitializer;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Exception\AbortMigration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The SQLite profile evolves through its own migration line. The upstream MySQL history
 * must never run against SQLite, a fresh baseline counts as fully migrated, and the
 * resulting schema must match the entity mappings.
 */
final class SqliteMigrationsTest extends KernelTestCase
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

    public function testSqliteProfileUsesItsOwnMigrationLine(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);

        /** @var DependencyFactory $dependencyFactory */
        $dependencyFactory = self::getContainer()->get('doctrine.migrations.dependency_factory');
        $configuration = $dependencyFactory->getConfiguration();
        self::assertSame(
            ['SqliteMigrations' => dirname(__DIR__, 2).'/migrations-sqlite'],
            $configuration->getMigrationDirectories(),
        );

        /** @var SqliteBaselineInitializer $baseline */
        $baseline = self::getContainer()->get(SqliteBaselineInitializer::class);
        $baseline->initialize();

        $migrate = new CommandTester((new Application(self::$kernel))->find('doctrine:migrations:migrate'));
        self::assertSame(Command::SUCCESS, $migrate->execute([], ['interactive' => false]), $migrate->getDisplay());

        $tables = $this->manager()->getConnection()->fetchFirstColumn("SELECT name FROM sqlite_master WHERE type = 'table'");
        self::assertContains('fewohbee_sqlite_migration_versions', $tables);
        self::assertNotContains('doctrine_migration_versions', $tables);

        $status = new CommandTester((new Application(self::$kernel))->find('doctrine:migrations:up-to-date'));
        self::assertSame(Command::SUCCESS, $status->execute([]), $status->getDisplay());
    }

    public function testMigrationLineRefusesADatabaseNotCreatedByTheBaseline(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);

        $migrate = new CommandTester((new Application(self::$kernel))->find('doctrine:migrations:migrate'));
        try {
            $migrate->execute([], ['interactive' => false]);
            self::fail('The SQLite migration line ran on a database without the SQLite baseline.');
        } catch (AbortMigration $exception) {
            self::assertStringContainsString('not created by the SQLite baseline', $exception->getMessage());
        }

        self::assertSame(
            [],
            $this->manager()->getConnection()->fetchFirstColumn("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'reservations'"),
        );
    }

    public function testFreshBaselineSchemaMatchesTheEntityMappings(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);

        /** @var SqliteBaselineInitializer $baseline */
        $baseline = self::getContainer()->get(SqliteBaselineInitializer::class);
        $baseline->initialize();

        foreach (['default', 'geo'] as $name) {
            /** @var ManagerRegistry $registry */
            $registry = self::getContainer()->get(ManagerRegistry::class);
            $manager = $registry->getManager($name);
            self::assertInstanceOf(EntityManagerInterface::class, $manager);

            $pending = (new SchemaTool($manager))->getUpdateSchemaSql($manager->getMetadataFactory()->getAllMetadata());
            self::assertSame([], $pending, sprintf('Schema drift on the "%s" connection.', $name));
        }
    }

    private function manager(): EntityManagerInterface
    {
        /** @var ManagerRegistry $registry */
        $registry = self::getContainer()->get(ManagerRegistry::class);
        $manager = $registry->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
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
