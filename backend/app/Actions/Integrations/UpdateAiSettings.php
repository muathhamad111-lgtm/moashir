<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Data\Integrations\IntegrationUpdate;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Integrations\IntegrationRejected;
use App\Models\Identity\User;
use App\Services\Integrations\IntegrationActivation;
use App\Services\Integrations\IntegrationNotifier;
use App\Services\Integrations\IntegrationRows;
use App\Services\Integrations\IntegrationSettingsUpdater;
use App\Services\Integrations\ProviderRefsWriter;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class UpdateAiSettings
{
    public function __construct(
        private IntegrationSettingsUpdater $updater,
        private IntegrationRows $rows,
        private ProviderRefsWriter $refs,
        private IntegrationNotifier $notifier,
    ) {}

    public function handle(User $actor, IntegrationUpdate $update): void
    {
        $shouldNotify = DB::transaction(function () use ($actor, $update): bool {
            $this->updater->begin(IntegrationKind::Ai, $update, $actor);
            $replaced = $this->updater->writeCredentials($update, $actor);
            $this->writeAcknowledgements($update, $actor);
            $this->updater->writeSettings(IntegrationKind::Ai, $update, $actor);
            $this->updater->applyMode(IntegrationKind::Ai, $update, $actor);
            $activationChanged = $this->updater->applyActivation(IntegrationKind::Ai, $update, 'active_provider');

            return $replaced !== [] || $activationChanged;
        });

        if ($shouldNotify) {
            $this->notifier->notify(NotificationCode::AiProviderUpdated, $actor->id);
        }
    }

    private function writeAcknowledgements(IntegrationUpdate $update, User $actor): void
    {
        foreach ([IntegrationProvider::OpenAi, IntegrationProvider::Anthropic] as $provider) {
            if (! $update->hasConfirmation($provider->value)) {
                continue;
            }

            $row = $this->rows->findOrNew($provider);
            $isAcknowledged = $update->confirmations[$provider->value];

            if (! $isAcknowledged && $row->is_active) {
                throw IntegrationRejected::acknowledgementRequired('providers.'.$provider->value.'.data_transfer_acknowledged');
            }

            if ($isAcknowledged === ($row->providerRef(IntegrationActivation::ACKNOWLEDGED_AT) !== null)) {
                continue;
            }

            $this->refs->put($row, [
                IntegrationActivation::ACKNOWLEDGED_AT => $isAcknowledged ? Date::now()->toIso8601String() : null,
                'data_transfer_acknowledged_by' => $isAcknowledged ? $actor->id : null,
            ], IntegrationKind::Ai->updatedAuditEvent());
        }
    }
}
