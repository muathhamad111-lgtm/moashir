<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Data\Integrations\IntegrationUpdate;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Exceptions\Integrations\IntegrationRejected;
use App\Models\Identity\User;
use App\Services\Integrations\IntegrationSettingsUpdater;
use App\Services\Integrations\SmtpHostGuard;
use App\Services\Settings\IntegrationSettings;
use Illuminate\Support\Facades\DB;

final readonly class UpdateEmailSettings
{
    public function __construct(
        private IntegrationSettingsUpdater $updater,
        private IntegrationSettings $settings,
        private SmtpHostGuard $hosts,
    ) {}

    public function handle(User $actor, IntegrationUpdate $update): void
    {
        $this->assertHostAllowed($update);

        DB::transaction(function () use ($actor, $update): void {
            $this->updater->begin(IntegrationKind::Email, $update, $actor);
            $this->updater->writeCredentials($update, $actor);
            $this->updater->writeSettings(IntegrationKind::Email, $update, $actor);
            $this->updater->applyMode(IntegrationKind::Email, $update, $actor);
            $this->assertTestMailbox();
            $this->updater->applyActivation(IntegrationKind::Email, $update, 'active');
        });
    }

    private function assertHostAllowed(IntegrationUpdate $update): void
    {
        foreach ($update->credentials as $credential) {
            $host = $credential->changes->publicConfig['host'] ?? null;

            if (is_string($host) && $this->hosts->publicAddressOf($host) === null) {
                throw IntegrationRejected::hostNotAllowed('host');
            }
        }
    }

    private function assertTestMailbox(): void
    {
        if ($this->settings->mode(IntegrationKind::Email) === IntegrationMode::Test && $this->settings->emailTestAddress() === null) {
            throw IntegrationRejected::testAddressRequired();
        }
    }
}
