<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\TemplatesFixtures;
use App\Entity\Template;
use App\Sqlite\SqliteBaselineInitializer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SqliteFirstRunLocalTemplatesTest extends KernelTestCase
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

    public function testFirstRunSeedsLocalTemplatesWithoutNetworkAndCanBeRepeatedSafely(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);

        /** @var SqliteBaselineInitializer $baseline */
        $baseline = self::getContainer()->get(SqliteBaselineInitializer::class);
        $baseline->initialize();

        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:first-run'));
        $exitCode = $tester->execute([
            '--username' => 'sqlite-admin',
            '--password' => 'safe-test-password',
            '--first-name' => 'SQLite',
            '--last-name' => 'Validation',
            '--email' => 'sqlite-validation@example.test',
            '--accommodation-name' => 'SQLite validation accommodation',
        ], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode);

        /** @var ManagerRegistry $registry */
        $registry = self::getContainer()->get(ManagerRegistry::class);
        $manager = $registry->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        $templates = $manager->getRepository(Template::class)->findAll();

        self::assertCount(7, $templates);
        self::assertContains('SQLite market-validation local seed', array_map(
            static fn (Template $template): string => $template->getText(),
            $templates,
        ));

        /** @var TemplatesFixtures $templatesFixtures */
        $templatesFixtures = self::getContainer()->get(TemplatesFixtures::class);
        $templatesFixtures->load($manager);
        self::assertSame(7, $manager->getRepository(Template::class)->count([]));

        $repeat = new CommandTester($application->find('app:first-run'));
        self::assertSame(Command::SUCCESS, $repeat->execute(['--if-not-initialized' => true], ['interactive' => false]));
        self::assertSame(7, $manager->getRepository(Template::class)->count([]));
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
