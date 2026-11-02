<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Integrations\IntegrationKind;
use App\Exceptions\Integrations\IntegrationRejected;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Audit\AuditRecorder;

final readonly class IntegrationActivation
{
    public const string ACKNOWLEDGED_AT = 'data_transfer_acknowledged_at';

    public function __construct(
        private IntegrationCredentialsResolver $resolver,
        private IntegrationRows $rows,
        private AuditRecorder $audit,
    ) {}

    public function activate(IntegrationCredential $row, string $field): bool
    {
        $this->assertActivatable($row, $field);

        if ($row->is_active) {
            return false;
        }

        $current = $this->rows->active($row->kind);

        if ($current instanceof IntegrationCredential) {
            $this->switchOff($current);
        }

        $this->switchOn($row);

        return true;
    }

    public function deactivate(IntegrationKind $kind): bool
    {
        $current = $this->rows->active($kind);

        if (! $current instanceof IntegrationCredential) {
            return false;
        }

        $this->switchOff($current);

        return true;
    }

    public function assertActivatable(IntegrationCredential $row, string $field): void
    {
        if (! $row->exists || ! $this->resolver->isComplete($row)) {
            throw IntegrationRejected::configurationIncomplete($field);
        }

        if ($row->kind === IntegrationKind::Payment && ! $row->hasPassingTest()) {
            throw IntegrationRejected::testRequired($field);
        }

        if ($row->kind === IntegrationKind::Ai && $row->providerRef(self::ACKNOWLEDGED_AT) === null) {
            throw IntegrationRejected::acknowledgementRequired($field);
        }
    }

    private function switchOn(IntegrationCredential $row): void
    {
        $row->forceFill(['is_active' => true, 'config_version' => $row->config_version + 1])->save();
        $this->audit->record(new AuditEntry(AuditEvent::IntegrationActivated, $row, ['is_active' => false], [
            'is_active' => true,
            'provider' => $row->provider->value,
            'mode' => $row->mode->value,
        ]));
    }

    private function switchOff(IntegrationCredential $row): void
    {
        $row->forceFill(['is_active' => false, 'config_version' => $row->config_version + 1])->save();
        $this->audit->record(new AuditEntry(AuditEvent::IntegrationDeactivated, $row, ['is_active' => true], [
            'is_active' => false,
            'provider' => $row->provider->value,
            'mode' => $row->mode->value,
        ]));
    }
}
