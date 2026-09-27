<?php

declare(strict_types=1);

namespace App\Command;

use App\Sqlite\SqliteBackupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        $this
            ->addArgument('target', InputArgument::OPTIONAL, 'Path of the backup file to create; it must not exist yet.')
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Write a timestamped backup into this directory instead (for scheduled runs).')
            ->addOption('keep', null, InputOption::VALUE_REQUIRED, 'With --dir: number of newest scheduled backups to keep.', '14');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $target = $input->getArgument('target');
        $directory = $input->getOption('dir');
        $keepGiven = $input->hasParameterOption('--keep');

        if ((null === $target) === (null === $directory) || (null !== $target && $keepGiven)) {
            $io->error('Pass either a target file, or --dir with an optional --keep.');

            return Command::INVALID;
        }

        try {
            if (null !== $directory) {
                $target = $this->backupService->backupToDirectory((string) $directory, (int) $input->getOption('keep'));
            } else {
                $this->backupService->backup((string) $target);
            }
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Backup written and verified: %s', $target));

        return Command::SUCCESS;
    }
}
