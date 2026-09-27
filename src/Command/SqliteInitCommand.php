<?php

declare(strict_types=1);

namespace App\Command;

use App\Sqlite\SqliteBaselineInitializer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sqlite:init',
    description: 'Creates the SQLite baseline schema on a fresh database; does nothing when it already exists.',
)]
final class SqliteInitCommand extends Command
{
    public function __construct(private readonly SqliteBaselineInitializer $initializer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->initializer->initialize();
        } catch (\LogicException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('SQLite database is on baseline %s.', SqliteBaselineInitializer::VERSION));

        return Command::SUCCESS;
    }
}
