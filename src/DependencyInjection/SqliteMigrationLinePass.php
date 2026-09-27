<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Keeps the upstream MySQL migration history out of the SQLite profile.
 *
 * Symfony merges migrations_paths from config/packages and the environment directory,
 * so the SQLite profile would otherwise see both the MySQL migrations and its own line.
 * Only the SQLite namespace stays registered.
 */
final class SqliteMigrationLinePass implements CompilerPassInterface
{
    public const SQLITE_NAMESPACE = 'SqliteMigrations';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('doctrine.migrations.configuration')) {
            return;
        }

        $definition = $container->getDefinition('doctrine.migrations.configuration');
        $calls = array_filter(
            $definition->getMethodCalls(),
            static fn (array $call): bool => 'addMigrationsDirectory' !== $call[0] || self::SQLITE_NAMESPACE === $call[1][0],
        );
        $definition->setMethodCalls(array_values($calls));
    }
}
