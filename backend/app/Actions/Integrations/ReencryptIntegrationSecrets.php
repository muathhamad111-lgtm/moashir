<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ReencryptIntegrationSecrets
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(): int
    {
        return DB::transaction(function (): int {
            $count = 0;

            foreach (IntegrationCredential::query()->whereNotNull('secrets')->orderBy('id')->lockForUpdate()->get() as $row) {
                $row->setAttribute('secrets', $row->secrets);
                IntegrationCredential::query()->whereKey($row->id)->toBase()->update(['secrets' => $row->getAttributes()['secrets']]);
                $this->audit->record(new AuditEntry(AuditEvent::IntegrationSecretsReencrypted, $row, [], ['secret_fields' => array_keys($row->secrets ?? [])]));
                $count++;
            }

            return $count;
        });
    }
}
