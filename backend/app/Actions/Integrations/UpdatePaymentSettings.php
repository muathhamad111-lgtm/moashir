<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Data\Integrations\IntegrationUpdate;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\PaymentConfirmation;
use App\Enums\Integrations\PaymentMethod;
use App\Enums\Settings\SettingKey;
use App\Exceptions\Integrations\IntegrationRejected;
use App\Models\Identity\User;
use App\Services\Integrations\IntegrationSettingsUpdater;
use App\Services\Settings\SettingsStore;
use App\Support\StringList;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class UpdatePaymentSettings
{
    public function __construct(private IntegrationSettingsUpdater $updater, private SettingsStore $store) {}

    public function handle(User $actor, IntegrationUpdate $update): void
    {
        DB::transaction(function () use ($actor, $update): void {
            $this->updater->begin(IntegrationKind::Payment, $update, $actor);
            $this->updater->writeCredentials($update, $actor);
            $this->updater->writeSettings(IntegrationKind::Payment, $update, $actor);
            $this->writeConfirmations($update, $actor);
            $this->assertMethodsConfirmed($update);
            $this->updater->applyMode(IntegrationKind::Payment, $update, $actor);
            $this->updater->applyActivation(IntegrationKind::Payment, $update, 'active');
        });
    }

    private function writeConfirmations(IntegrationUpdate $update, User $actor): void
    {
        foreach (PaymentConfirmation::cases() as $confirmation) {
            if (! $update->hasConfirmation($confirmation->value)) {
                continue;
            }

            $key = $confirmation->settingKey();
            $isConfirmed = $update->confirmations[$confirmation->value];
            $current = $this->store->get($key);

            if ($isConfirmed === is_array($current)) {
                continue;
            }

            $value = $isConfirmed ? ['confirmed_at' => Date::now()->toIso8601String(), 'confirmed_by_user_id' => $actor->id] : null;
            $this->store->put($key, $value, IntegrationKind::Payment->updatedAuditEvent(), $actor);
        }
    }

    private function assertMethodsConfirmed(IntegrationUpdate $update): void
    {
        foreach (StringList::from($this->store->get(SettingKey::PaymentMethods)) as $value) {
            $confirmation = PaymentMethod::tryFrom($value)?->requiredConfirmation();

            if ($confirmation instanceof PaymentConfirmation && ! is_array($this->store->get($confirmation->settingKey()))) {
                throw $update->hasSetting(SettingKey::PaymentMethods)
                    ? IntegrationRejected::methodRequiresConfirmation()
                    : IntegrationRejected::confirmationInUse('confirmations.'.$confirmation->value);
            }
        }
    }
}
