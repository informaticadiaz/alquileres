<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Doctrine\SqliteDateType;
use App\Kernel;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The operational SQLite profile: production settings of upstream (when@prod), the
 * SQLite protections of the test profile, and a persistent database whose location
 * comes from the environment instead of the repository.
 */
final class SqliteProdProfileTest extends TestCase
{
    private string $databasePath;
    private ?Kernel $kernel = null;
    private ?string $previousSecret = null;

    protected function setUp(): void
    {
        $directory = dirname(__DIR__, 2).'/var/sqlite-prod-test';
        if (!is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        $this->databasePath = $directory.'/fewohbee.sqlite';
        $this->removeDatabase();

        $_SERVER['FEWOHBEE_SQLITE_PATH'] = $_ENV['FEWOHBEE_SQLITE_PATH'] = $this->databasePath;
        // In operation both come from the service's environment file, never from the repository.
        $this->previousSecret = $_SERVER['APP_SECRET'] ?? null;
        $_SERVER['APP_SECRET'] = $_ENV['APP_SECRET'] = 'sqlite-prod-profile-test-secret';
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        unset($_SERVER['FEWOHBEE_SQLITE_PATH'], $_ENV['FEWOHBEE_SQLITE_PATH']);
        if (null === $this->previousSecret) {
            unset($_SERVER['APP_SECRET'], $_ENV['APP_SECRET']);
        } else {
            $_SERVER['APP_SECRET'] = $_ENV['APP_SECRET'] = $this->previousSecret;
        }
        $this->removeDatabase();
    }

    public function testProfileRunsProductionSettingsOnThePersistentSqliteDatabase(): void
    {
        // Debug mode is off, so a stale compiled container would hide config changes.
        // The running service uses its own deploy checkout, never this cache.
        (new Filesystem())->remove(dirname(__DIR__, 2).'/var/cache/sqlite_prod');
        $this->kernel = new Kernel('sqlite_prod', false);
        $this->kernel->boot();
        $container = $this->kernel->getContainer();

        self::assertFalse($container->has('test.service_container'), 'The operational profile must not enable the test framework.');

        /** @var ManagerRegistry $registry */
        $registry = $container->get('doctrine');
        $manager = $registry->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        $connection = $manager->getConnection();

        self::assertInstanceOf(SQLitePlatform::class, $connection->getDatabasePlatform());
        self::assertSame($this->databasePath, $connection->getParams()['path'] ?? null);
        self::assertSame(1, (int) $connection->fetchOne('PRAGMA foreign_keys'));
        self::assertSame('wal', $connection->fetchOne('PRAGMA journal_mode'));
        self::assertInstanceOf(SqliteDateType::class, Type::getType('date'));
        // Sessions stay inside the service's own directory and are garbage collected:
        // the system session directory is only cleaned for php-fpm/Apache.
        self::assertSame($this->kernel->getProjectDir().'/var/sessions/sqlite_prod', $container->getParameter('session.save_path'));
        self::assertGreaterThan(0, $container->getParameter('session.storage.options')['gc_probability'] ?? 0);
        // Inherited from upstream when@prod: Doctrine result caching is configured.
        self::assertNotNull($manager->getConfiguration()->getResultCache());

        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $init = new CommandTester($application->find('app:sqlite:init'));
        self::assertSame(Command::SUCCESS, $init->execute([]), $init->getDisplay());
        self::assertSame(Command::SUCCESS, $init->execute([]), 'Initializing twice must be a no-op. '.$init->getDisplay());

        $upToDate = new CommandTester($application->find('doctrine:migrations:up-to-date'));
        self::assertSame(Command::SUCCESS, $upToDate->execute([]), $upToDate->getDisplay());

        $tables = $connection->fetchFirstColumn("SELECT name FROM sqlite_master WHERE type = 'table'");
        self::assertContains('reservations', $tables);
        self::assertContains('fewohbee_sqlite_migration_versions', $tables);
        self::assertNotContains('doctrine_migration_versions', $tables);
    }

    private function removeDatabase(): void
    {
        foreach ([$this->databasePath, $this->databasePath.'-wal', $this->databasePath.'-shm'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
