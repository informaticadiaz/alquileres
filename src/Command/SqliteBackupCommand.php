<?php

declare(strict_types=1);

namespace App\Command;

use App\Sqlite\SqliteBackupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sqlite:backup',
    description: 'Writes a verified, consistent backup of the SQLite database to a new file.',
)]
final class SqliteBackupCommand extends Command
{
    public function __construct(private readonly SqliteBackupService $backupService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('target', InputArgument::REQUIRED, 'Path of the backup file to create; it must not exist yet.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $target = (string) $input->getArgument('target');

        try {
            $this->backupService->backup($target);
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Backup written and verified: %s', $target));

        return Command::SUCCESS;
    }
}
