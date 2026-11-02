<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\Audit\AuditEvent;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Settings\SettingKey;
use App\Exceptions\Integrations\IntegrationRejected;
use App\Models\Identity\User;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Settings\IntegrationSettings;
use App\Services\Settings\SettingsStore;
use App\Support\RequestContext;
use Illuminate\Contracts\Foundation\Application;

final readonly class IntegrationModeSwitcher
{
    public function __construct(
        private IntegrationSettings $settings,
        private SettingsStore $store,
        private IntegrationRows $rows,
        private IntegrationActivation $activation,
        private RequestContext $context,
        private Application $app,
    ) {}

    public function switch(IntegrationKind $kind, IntegrationMode $mode, User $actor, ?string $reason): bool
    {
        if ($this->settings->mode($kind) === $mode) {
            return false;
        }

        if ($this->needsReason($kind, $mode)) {
            $this->requireReason($reason);
        }

        $kind->hasModePerCredential() ? $this->switchPaymentRows($mode) : $this->moveRowsTo($kind, $mode);

        $this->store->put(SettingKey::modeOf($kind), $mode->value, AuditEvent::IntegrationModeChanged, $actor);

        return true;
    }

    public function needsReason(IntegrationKind $kind, IntegrationMode $mode): bool
    {
        return $kind === IntegrationKind::Payment && $mode->isTest() && $this->app->isProduction();
    }

    private function requireReason(?string $reason): void
    {
        if ($reason === null || trim($reason) === '') {
            throw IntegrationRejected::reasonRequired();
        }

        $this->context->recordReason($reason);
    }

    private function switchPaymentRows(IntegrationMode $mode): void
    {
        $target = $this->rows->findOrNew(IntegrationProvider::Moyasar, $mode);
        $isActive = $this->rows->active(IntegrationKind::Payment) instanceof IntegrationCredential;

        if ($isActive || $mode === IntegrationMode::Live) {
            $this->activation->assertActivatable($target, 'mode');
        }

        if ($isActive) {
            $this->activation->activate($target, 'mode');
        }
    }

    private function moveRowsTo(IntegrationKind $kind, IntegrationMode $mode): void
    {
        foreach (IntegrationCredential::query()->ofKind($kind)->get() as $row) {
            $row->forceFill(['mode' => $mode, 'config_version' => $row->config_version + 1])->save();
        }
    }
}
