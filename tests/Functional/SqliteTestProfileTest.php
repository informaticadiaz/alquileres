<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SqliteTestProfileTest extends KernelTestCase
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
        foreach ([$this->fixturePath, $this->fixturePath.'-wal', $this->fixturePath.'-shm'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();

        foreach ([$this->fixturePath, $this->fixturePath.'-wal', $this->fixturePath.'-shm'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function testSqliteProfileConfiguresBothConnectionsForAnIsolatedFixture(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);

        /** @var ManagerRegistry $registry */
        $registry = self::getContainer()->get(ManagerRegistry::class);
        $default = $registry->getConnection('default');
        $geo = $registry->getConnection('geo');

        self::assertInstanceOf(SQLitePlatform::class, $default->getDatabasePlatform());
        self::assertInstanceOf(SQLitePlatform::class, $geo->getDatabasePlatform());
        self::assertSame($this->fixturePath, $default->getParams()['path']);
        self::assertSame($this->fixturePath, $geo->getParams()['path']);
        self::assertSame(1, (int) $default->fetchOne('PRAGMA foreign_keys'));
        self::assertSame(1, (int) $geo->fetchOne('PRAGMA foreign_keys'));
        self::assertSame('wal', strtolower((string) $default->fetchOne('PRAGMA journal_mode')));
        self::assertSame('wal', strtolower((string) $geo->fetchOne('PRAGMA journal_mode')));
        self::assertSame(5000, (int) $default->fetchOne('PRAGMA busy_timeout'));
        self::assertSame(5000, (int) $geo->fetchOne('PRAGMA busy_timeout'));
    }
}
