<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Platform\DestructiveDatabaseCommand;
use App\Services\Platform\DestructiveCommandBaseline;
use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Input\InputOption;

final class DatabaseSafetyServiceProvider extends ServiceProvider
{
    public function boot(DestructiveCommandBaseline $baseline): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $baseline->apply();

        foreach (DestructiveDatabaseCommand::cases() as $command) {
            $this->app->resolving($command->commandClass(), $this->addConfirmationOption(...));
        }
    }

    private function addConfirmationOption(Command $command): void
    {
        if ($command->getDefinition()->hasOption(DestructiveDatabaseCommand::CONFIRMATION_OPTION)) {
            return;
        }

        $command->addOption(
            DestructiveDatabaseCommand::CONFIRMATION_OPTION,
            null,
            InputOption::VALUE_REQUIRED,
            'Local environment only: the exact name of the non-test database you intend to destroy',
        );
    }
}
