<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Integrations\ReencryptIntegrationSecrets;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:integrations:reencrypt')]
final class ReencryptIntegrationSecretsCommand extends Command
{
    protected $signature = 'moashir:integrations:reencrypt';

    protected $description = 'Re-encrypt every stored integration secret with the current APP_KEY (run after a key rotation, with the old key in APP_PREVIOUS_KEYS).';

    public function handle(ReencryptIntegrationSecrets $reencrypt): int
    {
        $this->components->info(sprintf('Re-encrypted the secrets of %d integration rows.', $reencrypt->handle()));

        return self::SUCCESS;
    }
}
