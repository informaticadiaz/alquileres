<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\WorkflowLog;
use App\Kernel;
use App\Sqlite\SqliteBaselineInitializer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * A scheduled workflow runs its action first and records the successful execution
 * afterwards. A cron pass that starts while a previous one is still inside that gap must
 * not execute the same workflow again.
 */
final class SqliteScheduledWorkflowsOverlapTest extends KernelTestCase
{
    private string $fixturePath;
    private ?Kernel $overlappingKernel = null;

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
        $this->overlappingKernel?->shutdown();
        $this->overlappingKernel = null;
        self::ensureKernelShutdown();
        $this->removeFixture();

        parent::tearDown();
    }

    public function testOverlappingCronPassDoesNotExecuteTheWorkflowTwice(): void
    {
        self::bootKernel(['environment' => 'sqlite_test']);
        /** @var SqliteBaselineInitializer $baseline */
        $baseline = self::getContainer()->get(SqliteBaselineInitializer::class);
        $baseline->initialize();
        $firstRun = new CommandTester((new Application(self::$kernel))->find('app:first-run'));
        self::assertSame(Command::SUCCESS, $firstRun->execute([
            '--username' => 'sqlite-admin',
            '--password' => 'safe-test-password',
            '--first-name' => 'SQLite',
            '--last-name' => 'Validation',
            '--email' => 'sqlite-validation@example.test',
            '--accommodation-name' => 'SQLite validation accommodation',
            '--load-sample-data' => true,
        ], ['interactive' => false]), $firstRun->getDisplay());

        $connection = $this->manager()->getConnection();
        // Exactly one reservation arrives in three days, so exactly one execution is due.
        $connection->executeStatement(
            'UPDATE reservations SET start_date = ?, end_date = ? WHERE id = (SELECT MIN(id) FROM reservations)',
            [new \DateTimeImmutable('today +3 days'), new \DateTimeImmutable('today +5 days')],
            [Types::DATE_IMMUTABLE, Types::DATE_IMMUTABLE],
        );
        $workflowId = $this->insertReminderWorkflow($connection);

        $overlappingOutput = null;
        $this->manager()->getEventManager()->addEventListener([Events::onFlush], new class($overlappingOutput, fn () => $this->runOverlappingPass()) {
            /** @param \Closure(): string $overlappingPass */
            public function __construct(private ?string &$output, private readonly \Closure $overlappingPass)
            {
            }

            public function onFlush(OnFlushEventArgs $args): void
            {
                if (null !== $this->output) {
                    return;
                }
                foreach ($args->getObjectManager()->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
                    if ($entity instanceof WorkflowLog) {
                        $this->output = ($this->overlappingPass)();

                        return;
                    }
                }
            }
        });

        $tester = new CommandTester((new Application(self::$kernel))->find('workflow:process-scheduled'));
        self::assertSame(Command::SUCCESS, $tester->execute([]));

        self::assertNotNull($overlappingOutput, 'The overlapping pass never ran inside the action/log gap.');
        self::assertGreaterThan(0, (int) $connection->fetchOne("SELECT COUNT(*) FROM workflow_logs WHERE workflow_id = ? AND status = 'success'", [$workflowId]));
        self::assertSame(
            1,
            (int) $connection->fetchOne("SELECT MAX(runs) FROM (SELECT COUNT(*) AS runs FROM workflow_logs WHERE workflow_id = ? AND status = 'success' GROUP BY entity_id)", [$workflowId]),
            'The workflow was executed twice by overlapping cron passes. Overlapping pass output: '.$overlappingOutput,
        );
        self::assertStringContainsString('already running', $overlappingOutput);
    }

    private function runOverlappingPass(): string
    {
        $this->overlappingKernel = new Kernel('sqlite_test', true);
        $this->overlappingKernel->boot();

        $tester = new CommandTester((new Application($this->overlappingKernel))->find('workflow:process-scheduled'));
        $tester->execute([]);

        return $tester->getDisplay();
    }

    private function insertReminderWorkflow(Connection $connection): int
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $connection->executeStatement(
            'INSERT INTO workflows (name, description, is_enabled, is_system, system_code, trigger_type, trigger_config, conditions, action_type, action_config, priority, created_at, updated_at)
             VALUES (:name, NULL, 1, 0, :code, :triggerType, :triggerConfig, :conditions, :actionType, :actionConfig, 0, :now, :now)',
            [
                'name' => 'SQLite overlap test',
                'code' => 'test_sqlite_overlap',
                'triggerType' => 'reservation.days_before_start',
                'triggerConfig' => json_encode([
                    'days' => 3,
                    'runOnDays' => 'daily',
                    'runAtHour' => 0,
                ], JSON_THROW_ON_ERROR),
                'conditions' => '[]',
                'actionType' => 'create_in_app_notification',
                'actionConfig' => json_encode(['severity' => 'info'], JSON_THROW_ON_ERROR),
                'now' => $now,
            ],
        );

        return (int) $connection->lastInsertId();
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
