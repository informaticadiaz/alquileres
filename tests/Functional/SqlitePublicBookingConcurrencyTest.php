<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Appartment;
use App\Entity\Customer;
use App\Entity\OnlineBookingConfig;
use App\Entity\Reservation;
use App\Entity\ReservationOrigin;
use App\Entity\ReservationStatus;
use App\Exception\PublicBookingException;
use App\Kernel;
use App\Service\OnlineBooking\OnlineBookingConfigService;
use App\Service\OnlineBooking\PublicAvailabilityService;
use App\Service\OnlineBooking\PublicBookingService;
use App\Sqlite\SqliteBaselineInitializer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A public booking checks availability and persists the reservation later in the same
 * request. A second writer that books the same room inside that window must not produce
 * an overbooking on the SQLite profile.
 */
final class SqlitePublicBookingConcurrencyTest extends KernelTestCase
{
    private string $fixturePath;
    private ?Kernel $concurrentKernel = null;
    /** @var array<string, array<int, int>> */
    private array $selection = [];
    private int $persons = 1;

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

    public function testConcurrentBookingInsideTheAvailabilityWindowDoesNotOverbookTheRoom(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);
        $this->prepareInstallation();

        $manager = $this->manager();
        $room = $manager->getRepository(Appartment::class)->findOneBy([]);
        self::assertInstanceOf(Appartment::class, $room);
        $roomId = (int) $room->getId();
        $dateFrom = new \DateTimeImmutable('2099-05-10');
        $dateTo = new \DateTimeImmutable('2099-05-12');
        self::assertSame(0, $this->countOverlapping($roomId, $dateFrom, $dateTo));

        /** @var PublicAvailabilityService $availabilityService */
        $availabilityService = self::getContainer()->get(PublicAvailabilityService::class);
        $offer = $availabilityService->getAvailabilityForRoom($room, $dateFrom, $dateTo)[0] ?? null;
        self::assertNotNull($offer, 'The sample room must be bookable in the test period.');
        $firstOption = reset($offer['occupancyOptions']);
        self::assertIsArray($firstOption);
        $persons = (int) $firstOption['persons'];
        $this->selection = [(string) $offer['typeKey'] => [$persons => 1]];
        $this->persons = $persons;

        // The second writer runs in its own kernel, container and database connection,
        // exactly when the first request has created its booker but not its reservation.
        $concurrentOutcome = null;
        $customerCreated = false;
        $eventManager = $manager->getEventManager();
        $eventManager->addEventListener([Events::postPersist, Events::postFlush], new class($customerCreated, $concurrentOutcome, fn () => $this->bookConcurrently($roomId, $dateFrom, $dateTo)) {
            /** @param \Closure(): string $concurrentBooking */
            public function __construct(private bool &$customerCreated, private ?string &$outcome, private readonly \Closure $concurrentBooking)
            {
            }

            public function postPersist(PostPersistEventArgs $args): void
            {
                if ($args->getObject() instanceof Customer) {
                    $this->customerCreated = true;
                }
            }

            public function postFlush(PostFlushEventArgs $args): void
            {
                if ($this->customerCreated && null === $this->outcome) {
                    $this->outcome = ($this->concurrentBooking)();
                }
            }
        });

        $firstOutcome = $this->book($this->getContainer()->get(PublicBookingService::class), $room, $dateFrom, $dateTo, 'first');

        self::assertNotNull($concurrentOutcome, 'The concurrent writer never ran inside the booking window (first writer: '.$firstOutcome.').');
        self::assertSame(
            1,
            $this->countOverlapping($roomId, $dateFrom, $dateTo),
            sprintf('Room overbooked (first writer: %s, concurrent writer: %s).', $firstOutcome, $concurrentOutcome),
        );
        // Writers are serialized: the second one waits for the lock and gives up after
        // busy_timeout instead of booking against a stale availability snapshot.
        self::assertSame('booked', $firstOutcome);
        self::assertSame('rejected: '.PublicBookingException::class.': online_booking.error.booking_busy', $concurrentOutcome);
        /** @var TranslatorInterface $translator */
        $translator = self::getContainer()->get(TranslatorInterface::class);
        foreach (['de', 'en'] as $locale) {
            self::assertNotSame('online_booking.error.booking_busy', $translator->trans('online_booking.error.booking_busy', [], null, $locale));
        }
        self::assertSame(1, $manager->getRepository(Customer::class)->count(['firstname' => 'First']));
        self::assertSame(0, $manager->getRepository(Customer::class)->count(['firstname' => 'Concurrent']));
        self::assertSame([], $manager->getConnection()->fetchAllAssociative('PRAGMA foreign_key_check'));
    }

    public function testDbalTransactionsReserveTheWriteLockWhenTheyBegin(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);
        /** @var SqliteBaselineInitializer $baseline */
        $baseline = self::getContainer()->get(SqliteBaselineInitializer::class);
        $baseline->initialize();

        $connection = $this->manager()->getConnection();
        $otherWriter = new \PDO('sqlite:'.$this->fixturePath);
        $otherWriter->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $otherWriter->exec('PRAGMA busy_timeout = 0');

        // A deferred BEGIN would only take the lock on its first write, letting a
        // read-then-write transaction fail late with SQLITE_BUSY instead of waiting.
        $connection->beginTransaction();
        try {
            $otherWriter->exec('BEGIN IMMEDIATE');
            $otherWriter->exec('ROLLBACK');
            self::fail('The DBAL transaction did not reserve the SQLite write lock.');
        } catch (\PDOException $exception) {
            self::assertStringContainsString('database is locked', $exception->getMessage());
        } finally {
            $connection->rollBack();
        }

        $otherWriter->exec('BEGIN IMMEDIATE');
        $otherWriter->exec('ROLLBACK');
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

        $manager = $this->manager();
        $status = $manager->getRepository(ReservationStatus::class)->findOneBy([]);
        $origin = $manager->getRepository(ReservationOrigin::class)->findOneBy([]);
        self::assertInstanceOf(ReservationStatus::class, $status);
        self::assertInstanceOf(ReservationOrigin::class, $origin);

        /** @var OnlineBookingConfigService $configService */
        $configService = self::getContainer()->get(OnlineBookingConfigService::class);
        $config = $configService->getConfig();
        $config->setEnabled(true);
        $config->setBookingMode(OnlineBookingConfig::BOOKING_MODE_BOOKING);
        $config->setBookingReservationStatusId($status->getId());
        $config->setInquiryReservationStatusId($status->getId());
        $config->setReservationOriginId($origin->getId());
        $manager->flush();
    }

    private function bookConcurrently(int $roomId, \DateTimeImmutable $dateFrom, \DateTimeImmutable $dateTo): string
    {
        $this->concurrentKernel = new Kernel('sqlite_test', true);
        $this->concurrentKernel->boot();
        $container = $this->concurrentKernel->getContainer()->get('test.service_container');

        /** @var ManagerRegistry $registry */
        $registry = $container->get(ManagerRegistry::class);
        $room = $registry->getManager()->find(Appartment::class, $roomId);
        self::assertInstanceOf(Appartment::class, $room);

        return $this->book($container->get(PublicBookingService::class), $room, $dateFrom, $dateTo, 'concurrent');
    }

    private function book(PublicBookingService $service, Appartment $room, \DateTimeImmutable $dateFrom, \DateTimeImmutable $dateTo, string $label): string
    {
        try {
            $service->createBooking($dateFrom, $dateTo, $this->persons, 1, $this->selection, [
                'salutation' => 'Mx',
                'firstname' => ucfirst($label),
                'lastname' => 'Writer',
                'email' => $label.'-writer@example.test',
                'address' => 'Test street 1',
                'zip' => '12345',
                'city' => 'Test city',
                'country' => 'DE',
            ], [], [], $room);

            return 'booked';
        } catch (\Throwable $exception) {
            return 'rejected: '.$exception::class.': '.$exception->getMessage();
        }
    }

    private function countOverlapping(int $roomId, \DateTimeImmutable $dateFrom, \DateTimeImmutable $dateTo): int
    {
        return (int) $this->manager()->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reservation::class, 'r')
            ->where('IDENTITY(r.appartment) = :room')
            ->andWhere('r.startDate < :end AND r.endDate > :start')
            ->setParameter('room', $roomId)
            ->setParameter('start', $dateFrom->format('Y-m-d'))
            ->setParameter('end', $dateTo->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();
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
