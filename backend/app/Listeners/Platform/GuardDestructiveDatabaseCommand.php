<?php

declare(strict_types=1);

namespace App\Listeners\Platform;

use App\Data\Platform\DestructiveCommandTarget;
use App\Enums\Platform\DestructiveDatabaseCommand;
use App\Services\Platform\ConnectionDatabaseNames;
use App\Services\Platform\DestructiveCommandGuard;
use App\Services\Platform\DestructiveCommandLock;
use App\Support\ScalarValue;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\Console\Input\InputInterface;

final readonly class GuardDestructiveDatabaseCommand
{
    public function __construct(
        private Application $app,
        private DestructiveCommandGuard $guard,
        private ConnectionDatabaseNames $databases,
        private DestructiveCommandLock $lock,
    ) {}

    public function handle(CommandStarting $event): void
    {
        $command = DestructiveDatabaseCommand::tryFrom($event->command);

        if ($command === null || ($command->isSeeding() && ! $this->isForced($event->input))) {
            return;
        }

        $target = new DestructiveCommandTarget(
            ScalarValue::string($this->app->environment()),
            $this->databases->of($this->stringOption($event->input, 'database')),
            $this->stringOption($event->input, DestructiveDatabaseCommand::CONFIRMATION_OPTION),
        );
        $verdict = $this->guard->verdict($target);

        $this->lock->apply($verdict);

        if (! $verdict->isAllowed()) {
            $event->output->writeln(sprintf('<error>%s</error>', $verdict->explain($target)));
        }
    }

    private function isForced(InputInterface $input): bool
    {
        return $input->hasOption('force') && $input->getOption('force') === true;
    }

    private function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->hasOption($name) ? $input->getOption($name) : null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
