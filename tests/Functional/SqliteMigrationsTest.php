<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Sqlite\SqliteBaselineInitializer;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Exception\AbortMigration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;
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

    public function testBaselineMigrationStateSurvivesAFreshProcess(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);
        /** @var SqliteBaselineInitializer $baseline */
        $baseline = self::getContainer()->get(SqliteBaselineInitializer::class);
        $baseline->initialize();

        // A new kernel stands for the next process: nothing may be remembered in memory.
        self::ensureKernelShutdown();
        self::bootKernel(['environment' => 'sqlite_test']);

        $upToDate = new CommandTester((new Application(self::$kernel))->find('doctrine:migrations:up-to-date'));
        self::assertSame(Command::SUCCESS, $upToDate->execute([]), $upToDate->getDisplay());
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

        // Same check an operator runs. It must go through Application::run(): the
        // migrations bundle hides its own table from schema commands on the console event.
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        foreach (['default', 'geo'] as $name) {
            $validate = new ApplicationTester($application);
            self::assertSame(
                Command::SUCCESS,
                $validate->run(['command' => 'doctrine:schema:validate', '--em' => $name, '--skip-mapping' => true, '-v' => true]),
                sprintf('Schema drift on the "%s" connection: %s', $name, $validate->getDisplay()),
            );
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
