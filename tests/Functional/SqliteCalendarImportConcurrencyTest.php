<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Dto\Ics\IcsOccurrence;
use App\Entity\Appartment;
use App\Entity\CalendarSyncImport;
use App\Entity\Reservation;
use App\Entity\ReservationOrigin;
use App\Entity\ReservationStatus;
use App\Kernel;
use App\Service\Calendar\Sync\ImportedReservationSynchronizer;
use App\Sqlite\SqliteBaselineInitializer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * An iCal import looks up the reservation by its portal UID and creates it when missing.
 * A scheduled sync and a forced manual sync of the same feed running at the same time
 * must not import the same portal booking twice.
 */
final class SqliteCalendarImportConcurrencyTest extends KernelTestCase
{
    public const UID = 'sqlite-concurrency-test@portal.example';

    private string $fixturePath;
    private ?Kernel $concurrentKernel = null;

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
        $this->concurrentKernel?->shutdown();
        $this->concurrentKernel = null;
        self::ensureKernelShutdown();
        $this->removeFixture();

        parent::tearDown();
    }

    public function testConcurrentSyncOfTheSameEventImportsItOnce(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);
        $this->prepareInstallation();
        $importId = $this->createImport();

        $manager = $this->manager();
        $import = $manager->find(CalendarSyncImport::class, $importId);
        self::assertInstanceOf(CalendarSyncImport::class, $import);

        // The second sync runs in its own kernel and connection, right when the first one
        // has decided to create the reservation but has not written it yet.
        $concurrentOutcome = null;
        $manager->getEventManager()->addEventListener([Events::onFlush], new class($concurrentOutcome, fn () => $this->syncConcurrently($importId)) {
            /** @param \Closure(): string $concurrentSync */
            public function __construct(private ?string &$outcome, private readonly \Closure $concurrentSync)
            {
            }

            public function onFlush(OnFlushEventArgs $args): void
            {
                if (null !== $this->outcome) {
                    return;
                }
                foreach ($args->getObjectManager()->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
                    if ($entity instanceof Reservation && SqliteCalendarImportConcurrencyTest::UID === $entity->getRefUid()) {
                        $this->outcome = ($this->concurrentSync)();

                        return;
                    }
                }
            }
        });

        /** @var ImportedReservationSynchronizer $synchronizer */
        $synchronizer = self::getContainer()->get(ImportedReservationSynchronizer::class);
        $firstOutcome = $this->synchronize($synchronizer, $import);

        self::assertNotNull($concurrentOutcome, 'The concurrent sync never ran inside the import window (first sync: '.$firstOutcome.').');
        self::assertSame(
            1,
            $manager->getRepository(Reservation::class)->count(['refUid' => self::UID]),
            sprintf('Portal booking imported twice (first sync: %s, concurrent sync: %s).', $firstOutcome, $concurrentOutcome),
        );
        self::assertSame('Synchronized', $firstOutcome);
        self::assertStringContainsString('database is locked', $concurrentOutcome);
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

    private function createImport(): int
    {
        $manager = $this->manager();
        $apartment = $manager->getRepository(Appartment::class)->findOneBy([]);
        $origin = $manager->getRepository(ReservationOrigin::class)->findOneBy([]);
        $status = $manager->getRepository(ReservationStatus::class)->findOneBy([]);
        self::assertInstanceOf(Appartment::class, $apartment);
        self::assertInstanceOf(ReservationOrigin::class, $origin);
        self::assertInstanceOf(ReservationStatus::class, $status);

        $import = new CalendarSyncImport();
        $import->setName('SQLite concurrency test feed');
        $import->setUrl('https://portal.example/calendar.ics');
        $import->setIsActive(true);
        $import->setConflictStrategy(CalendarSyncImport::CONFLICT_SKIP);
        $import->setApartment($apartment);
        $import->setReservationOrigin($origin);
        $import->setReservationStatus($status);
        $manager->persist($import);
        $manager->flush();

        return (int) $import->getId();
    }

    private function syncConcurrently(int $importId): string
    {
        $this->concurrentKernel = new Kernel('sqlite_test', true);
        $this->concurrentKernel->boot();
        $container = $this->concurrentKernel->getContainer()->get('test.service_container');

        /** @var ManagerRegistry $registry */
        $registry = $container->get(ManagerRegistry::class);
        $import = $registry->getManager()->find(CalendarSyncImport::class, $importId);
        self::assertInstanceOf(CalendarSyncImport::class, $import);

        return $this->synchronize($container->get(ImportedReservationSynchronizer::class), $import);
    }

    private function synchronize(ImportedReservationSynchronizer $synchronizer, CalendarSyncImport $import): string
    {
        $event = new IcsOccurrence(
            self::UID,
            'Portal booking',
            '',
            new \DateTimeImmutable('2099-07-01'),
            new \DateTimeImmutable('2099-07-04'),
            true,
        );

        try {
            return $synchronizer->synchronize($import, $event)->name;
        } catch (\Throwable $exception) {
            return 'rejected: '.$exception::class.': '.$exception->getMessage();
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
