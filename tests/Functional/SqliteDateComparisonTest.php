<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Appartment;
use App\Entity\Enum\ModifierType;
use App\Entity\Enum\RoomBlockSource;
use App\Entity\Enum\TaxCalculationMode;
use App\Entity\GuestCategory;
use App\Entity\GuestCategoryModifier;
use App\Entity\Reservation;
use App\Entity\RoomBlock;
use App\Entity\TouristTax;
use App\Repository\GuestCategoryModifierRepository;
use App\Repository\RoomBlockRepository;
use App\Repository\TouristTaxRepository;
use App\Sqlite\SqliteBaselineInitializer;
use App\Workflow\Trigger\ReservationDaysBeforeStartTrigger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * SQLite compares dates as text. DQL parameters built from DateTime objects are bound as
 * "Y-m-d H:i:s", so date columns must be stored in a format that compares consistently
 * against them, or date-based queries silently match nothing.
 */
final class SqliteDateComparisonTest extends KernelTestCase
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

    public function testDateColumnsCompareCorrectlyAgainstDateTimeParameters(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);
        $this->prepareInstallation();

        $manager = $this->manager();
        $reservation = $manager->getRepository(Reservation::class)->findOneBy([], ['id' => 'ASC']);
        self::assertInstanceOf(Reservation::class, $reservation);
        $arrival = new \DateTime('today +3 days');
        $reservation->setStartDate(clone $arrival);
        $reservation->setEndDate(new \DateTime('today +5 days'));
        $manager->flush();
        $manager->clear();

        // A reminder "three days before arrival" must find the reservation.
        /** @var ReservationDaysBeforeStartTrigger $trigger */
        $trigger = self::getContainer()->get(ReservationDaysBeforeStartTrigger::class);
        $matches = $trigger->findMatchingIds($manager, ['days' => 3, 'runOnDays' => 'daily', 'runAtHour' => 0]);
        self::assertContains($reservation->getId(), $matches);

        // Boundaries: a stay ending on the arrival day does not overlap it, one starting
        // on the arrival day does.
        $overlapping = static fn (\DateTimeInterface $start, \DateTimeInterface $end): int => (int) $manager->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reservation::class, 'r')
            ->where('r.id = :id')
            ->andWhere('r.startDate < :end AND r.endDate > :start')
            ->setParameter('id', $reservation->getId())
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
        self::assertSame(0, $overlapping(new \DateTimeImmutable('today +1 day'), new \DateTimeImmutable('today +3 days')));
        self::assertSame(0, $overlapping(new \DateTimeImmutable('today +5 days'), new \DateTimeImmutable('today +7 days')));
        self::assertSame(1, $overlapping(new \DateTimeImmutable('today +4 days'), new \DateTimeImmutable('today +6 days')));

        // Reading the stored value back still yields a plain date.
        $reloaded = $manager->find(Reservation::class, $reservation->getId());
        self::assertInstanceOf(Reservation::class, $reloaded);
        self::assertSame($arrival->format('Y-m-d'), $reloaded->getStartDate()->format('Y-m-d'));
        self::assertSame('00:00:00', $reloaded->getStartDate()->format('H:i:s'));
    }

    public function testRepositoriesMatchDateColumnsOnTheBoundaryDay(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);
        $this->prepareInstallation();

        $manager = $this->manager();
        $apartment = $manager->getRepository(Appartment::class)->findOneBy([]);
        $guestCategory = $manager->getRepository(GuestCategory::class)->findOneBy([]);
        self::assertInstanceOf(Appartment::class, $apartment);
        self::assertInstanceOf(GuestCategory::class, $guestCategory);
        $day = new \DateTimeImmutable('2099-06-10');

        $block = new RoomBlock();
        $block->setAppartment($apartment);
        $block->setStartDate($day);
        $block->setEndDate($day->modify('+2 days'));
        $block->setReason('Boundary test');
        $block->setSource(RoomBlockSource::MANUAL);
        $manager->persist($block);

        $tax = new TouristTax();
        $tax->setName('Boundary test tax');
        $tax->setValidFrom(\DateTime::createFromImmutable($day));
        $tax->setValidTo(\DateTime::createFromImmutable($day));
        $tax->setActive(true);
        $tax->setIncludesVat(true);
        $tax->setAppliesOnlyToAdult(false);
        $tax->setSortOrder(0);
        $tax->setCalculationMode(TaxCalculationMode::PER_NIGHT_FLAT);
        $manager->persist($tax);

        $modifier = new GuestCategoryModifier();
        $modifier->setCategory($guestCategory);
        $modifier->setType(ModifierType::DISCOUNT_PERCENT);
        $modifier->setValue('10');
        $modifier->setValidFrom(\DateTime::createFromImmutable($day));
        $modifier->setValidTo(\DateTime::createFromImmutable($day));
        $modifier->setActive(true);
        $modifier->setSortOrder(0);
        $manager->persist($modifier);
        $manager->flush();

        /** @var RoomBlockRepository $blocks */
        $blocks = $manager->getRepository(RoomBlock::class);
        // Inclusive bounds: a table view ending on the block's first day shows the block.
        self::assertContains($block, $blocks->findForApartments($day->modify('-3 days'), $day, [$apartment]));
        // Exclusive bounds: a stay departing on the block's first day does not collide.
        self::assertSame([], $blocks->findOverlappingForApartment($apartment, $day->modify('-2 days'), $day));
        self::assertSame([], $blocks->findOverlappingByApartmentIds($day->modify('+2 days'), $day->modify('+4 days'), [(int) $apartment->getId()]));
        self::assertSame([], $blocks->findFiltered($day->modify('+2 days'), $day->modify('+4 days')));
        self::assertSame([], $blocks->findForPeriod($day->modify('+2 days'), $day->modify('+4 days')));

        /** @var TouristTaxRepository $taxes */
        $taxes = $manager->getRepository(TouristTax::class);
        self::assertContains($tax, $taxes->findActiveForSubsidiaryInRange(null, $day, $day));

        /** @var GuestCategoryModifierRepository $modifiers */
        $modifiers = $manager->getRepository(GuestCategoryModifier::class);
        self::assertContains($modifier, $modifiers->findActiveOn($day));
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

    private function removeFixture(): void
    {
        foreach ([$this->fixturePath, $this->fixturePath.'-wal', $this->fixturePath.'-shm'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
