<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\GeoEntity\PostalCodeData;
use App\Sqlite\SqliteBaselineInitializer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SqlitePostalcodeImportTest extends KernelTestCase
{
    private string $fixturePath;
    private string $importPath;

    protected function setUp(): void
    {
        parent::setUp();

        $fixtureDirectory = dirname(__DIR__, 2).'/var/sqlite-test';
        if (!is_dir($fixtureDirectory)) {
            mkdir($fixtureDirectory, 0770, true);
        }

        $this->fixturePath = $fixtureDirectory.'/fewohbee.sqlite';
        $this->importPath = $fixtureDirectory.'/postalcodes.tsv';
        $this->removeFixture();

        file_put_contents($this->importPath, implode("\n", [
            "DE\t01067\tDresden\tSachsen\tSN",
            "DE\t10115\tBerlin\tBerlin\tBE",
            "AT\t1010\tWien\t\t",
            'incomplete line',
        ])."\n");
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        $this->removeFixture();

        parent::tearDown();
    }

    public function testImportCanOverridePreviousDataWithoutMysqlStatements(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);

        /** @var SqliteBaselineInitializer $baseline */
        $baseline = self::getContainer()->get(SqliteBaselineInitializer::class);
        $baseline->initialize();

        $application = new Application(self::$kernel);

        $first = new CommandTester($application->find('app:import-postalcodedata'));
        self::assertSame(Command::SUCCESS, $first->execute(['file' => $this->importPath]));
        self::assertSame(3, $this->geoManager()->getRepository(PostalCodeData::class)->count([]));

        $override = new CommandTester($application->find('app:import-postalcodedata'));
        self::assertSame(Command::SUCCESS, $override->execute(['file' => $this->importPath, '--override' => true]));
        self::assertSame(3, $this->geoManager()->getRepository(PostalCodeData::class)->count([]));

        $connection = $this->geoManager()->getConnection();
        self::assertSame(1, (int) $connection->fetchOne('PRAGMA foreign_keys'));
        self::assertSame([], $connection->fetchAllAssociative('PRAGMA foreign_key_check'));
    }

    private function geoManager(): EntityManagerInterface
    {
        /** @var ManagerRegistry $registry */
        $registry = self::getContainer()->get(ManagerRegistry::class);
        $manager = $registry->getManager('geo');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    private function removeFixture(): void
    {
        foreach ([$this->fixturePath, $this->fixturePath.'-wal', $this->fixturePath.'-shm', $this->importPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
