<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\BookingBatch;
use App\Entity\Invoice;
use App\Kernel;
use App\Service\BookingJournal\BookingJournalService;
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
 * Journal entries are grouped into one batch per month; the batch is looked up and, when
 * missing, created before the entries are flushed. Two writers booking into a new month at
 * the same time must still end up with a single batch for that month on SQLite.
 */
final class SqliteBookingJournalConcurrencyTest extends KernelTestCase
{
    private const YEAR = 2099;
    private const MONTH = 3;

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

    public function testConcurrentEntriesIntoANewMonthShareASingleBatch(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);
        $this->prepareInstallation();

        $manager = $this->manager();
        $invoice = $manager->getRepository(Invoice::class)->findOneBy([]);
        self::assertInstanceOf(Invoice::class, $invoice);
        $invoiceId = (int) $invoice->getId();
        self::assertSame(0, $this->countBatches());

        // The second writer runs in its own kernel and connection, right when the first
        // one has decided to create the month batch but has not written it yet.
        $concurrentOutcome = null;
        $manager->getEventManager()->addEventListener([Events::onFlush], new class($concurrentOutcome, fn () => $this->bookConcurrently($invoiceId)) {
            /** @param \Closure(): string $concurrentBooking */
            public function __construct(private ?string &$outcome, private readonly \Closure $concurrentBooking)
            {
            }

            public function onFlush(OnFlushEventArgs $args): void
            {
                if (null !== $this->outcome) {
                    return;
                }
                foreach ($args->getObjectManager()->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
                    if ($entity instanceof BookingBatch) {
                        $this->outcome = ($this->concurrentBooking)();

                        return;
                    }
                }
            }
        });

        /** @var BookingJournalService $journal */
        $journal = self::getContainer()->get(BookingJournalService::class);
        $firstOutcome = $this->book($journal, $invoice);

        self::assertNotNull($concurrentOutcome, 'The concurrent writer never ran inside the batch window (first writer: '.$firstOutcome.').');
        self::assertSame(
            1,
            $this->countBatches(),
            sprintf('Duplicate month batch (first writer: %s, concurrent writer: %s).', $firstOutcome, $concurrentOutcome),
        );
        self::assertSame('booked', $firstOutcome);
        self::assertStringContainsString('database is locked', $concurrentOutcome);
        self::assertSame([], $manager->getConnection()->fetchAllAssociative('PRAGMA foreign_key_check'));
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

    private function bookConcurrently(int $invoiceId): string
    {
        $this->concurrentKernel = new Kernel('sqlite_test', true);
        $this->concurrentKernel->boot();
        $container = $this->concurrentKernel->getContainer()->get('test.service_container');

        /** @var ManagerRegistry $registry */
        $registry = $container->get(ManagerRegistry::class);
        $invoice = $registry->getManager()->find(Invoice::class, $invoiceId);
        self::assertInstanceOf(Invoice::class, $invoice);

        return $this->book($container->get(BookingJournalService::class), $invoice);
    }

    private function book(BookingJournalService $journal, Invoice $invoice): string
    {
        try {
            $journal->createEntriesFromInvoice($invoice, bookingDate: new \DateTimeImmutable(sprintf('%d-%02d-15', self::YEAR, self::MONTH)));

            return 'booked';
        } catch (\Throwable $exception) {
            return 'rejected: '.$exception::class.': '.$exception->getMessage();
        }
    }

    private function countBatches(): int
    {
        return $this->manager()->getRepository(BookingBatch::class)->count(['year' => self::YEAR, 'month' => self::MONTH]);
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
