<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Integrations\ImportEnvironmentCredentials;
use App\Enums\Integrations\IntegrationKind;
use App\Exceptions\Console\IntegrationCommandRejected;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:integrations:import-env')]
final class ImportIntegrationEnvironmentCommand extends Command
{
    protected $signature = 'moashir:integrations:import-env {kind : payment, sms, email or ai}';

    protected $description = 'Copy the bootstrap credentials of one integration from the environment into an encrypted settings row (audited), so they can be removed from .env.';

    public function handle(ImportEnvironmentCredentials $import): int
    {
        $kind = IntegrationKind::tryFrom((string) $this->argument('kind'));

        if (! $kind instanceof IntegrationKind) {
            $this->components->error('The kind must be one of: '.implode(', ', IntegrationKind::values()).'.');

            return self::INVALID;
        }

        try {
            $row = $import->handle($kind);
        } catch (IntegrationCommandRejected $rejection) {
            $this->components->error($rejection->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Imported %s (%s, %s) into the settings. Remove the values from .env now.', $kind->value, $row->provider->value, $row->mode->value));

        return self::SUCCESS;
    }
}
