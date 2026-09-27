<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Customer;
use App\Entity\Reservation;
use App\Sqlite\SqliteBaselineInitializer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * A SQLite backup must be a consistent single-file snapshot: it contains everything
 * committed (including pages still in the WAL), nothing uncommitted, passes the integrity
 * checks and restores into a working installation.
 */
final class SqliteBackupTest extends KernelTestCase
{
    private string $fixturePath;
    private string $backupPath;

    protected function setUp(): void
    {
        parent::setUp();

        $fixtureDirectory = dirname(__DIR__, 2).'/var/sqlite-test';
        if (!is_dir($fixtureDirectory)) {
            mkdir($fixtureDirectory, 0770, true);
        }

        $this->fixturePath = $fixtureDirectory.'/fewohbee.sqlite';
        $this->backupPath = $fixtureDirectory.'/backup/fewohbee-backup.sqlite';
        $this->removeFiles();
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        $this->removeFiles();

        parent::tearDown();
    }

    public function testBackupIsAConsistentSnapshotThatRestores(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);
        $this->prepareInstallation();

        $connection = $this->manager()->getConnection();
        $reservations = (int) $connection->fetchOne('SELECT COUNT(*) FROM reservations');
        self::assertGreaterThan(0, $reservations);
        // Committed changes that have not been checkpointed yet live only in the WAL.
        $connection->executeStatement("UPDATE customers SET firstname = 'Committed in WAL' WHERE id = (SELECT MIN(id) FROM customers)");
        self::assertGreaterThan(0, filesize($this->fixturePath.'-wal'));

        // An uncommitted write of another writer must not reach the backup.
        $otherWriter = new \PDO('sqlite:'.$this->fixturePath);
        $otherWriter->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $otherWriter->exec('BEGIN IMMEDIATE');
        $otherWriter->exec("UPDATE customers SET lastname = 'Uncommitted' WHERE id = (SELECT MIN(id) FROM customers)");

        try {
            $tester = new CommandTester((new Application(self::$kernel))->find('app:sqlite:backup'));
            $exitCode = $tester->execute(['target' => $this->backupPath]);
        } finally {
            $otherWriter->exec('ROLLBACK');
        }

        self::assertSame(Command::SUCCESS, $exitCode, $tester->getDisplay());
        self::assertFileExists($this->backupPath);
        self::assertFileDoesNotExist($this->backupPath.'-wal');

        $backup = new \PDO('sqlite:'.$this->backupPath);
        $backup->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        self::assertSame('ok', $backup->query('PRAGMA integrity_check')->fetchColumn());
        self::assertSame([], $backup->query('PRAGMA foreign_key_check')->fetchAll());
        self::assertSame($reservations, (int) $backup->query('SELECT COUNT(*) FROM reservations')->fetchColumn());
        self::assertSame(1, (int) $backup->query("SELECT COUNT(*) FROM customers WHERE firstname = 'Committed in WAL'")->fetchColumn());
        self::assertSame(0, (int) $backup->query("SELECT COUNT(*) FROM customers WHERE lastname = 'Uncommitted'")->fetchColumn());
        $backup = null;

        // A second backup never overwrites an existing file.
        $again = new CommandTester((new Application(self::$kernel))->find('app:sqlite:backup'));
        self::assertSame(Command::FAILURE, $again->execute(['target' => $this->backupPath]));

        // Restore: with the application stopped, the backup replaces the database file.
        self::ensureKernelShutdown();
        $this->removeDatabase();
        copy($this->backupPath, $this->fixturePath);

        self::bootKernel(['environment' => 'sqlite_test']);
        $manager = $this->manager();
        self::assertSame($reservations, $manager->getRepository(Reservation::class)->count([]));
        self::assertSame(1, $manager->getRepository(Customer::class)->count(['firstname' => 'Committed in WAL']));
        self::assertSame('wal', $manager->getConnection()->fetchOne('PRAGMA journal_mode'));
        self::assertSame('ok', $manager->getConnection()->fetchOne('PRAGMA integrity_check'));
    }

    private function prepareInstallation(): void
    {
        /** @var SqliteBaselineInitializer $baseline */
        $baseline = self::getContainer()->get(SqliteBaselineInitializer::class);
        $baseline->initialize();

        $tester = new CommandTester((new Application(self::$kernel))->find('app:first-run'));
        self::assertSame(Command::SUCCESS, $tester->execute([
            '--username' => 'sqlite-admin',
            '--password' => 'safe-test-password',
            '--first-name' => 'SQLite',
            '--last-name' => 'Validation',
            '--email' => 'sqlite-validation@example.test',
            '--accommodation-name' => 'SQLite validation accommodation',
            '--load-sample-data' => true,
        ], ['interactive' => false]), $tester->getDisplay());
    }

    private function manager(): EntityManagerInterface
    {
        /** @var ManagerRegistry $registry */
        $registry = self::getContainer()->get(ManagerRegistry::class);
        $manager = $registry->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    private function removeDatabase(): void
    {
        foreach ([$this->fixturePath, $this->fixturePath.'-wal', $this->fixturePath.'-shm'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function removeFiles(): void
    {
        $this->removeDatabase();
        foreach ([$this->backupPath, $this->backupPath.'-wal', $this->backupPath.'-shm'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir(dirname($this->backupPath))) {
            rmdir(dirname($this->backupPath));
        }
    }
}
