<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Audit\AuditRecorder;

final readonly class ProviderRefsWriter
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $changes
     */
    public function put(IntegrationCredential $row, array $changes, AuditEvent $event): bool
    {
        $refs = $row->provider_refs;
        $before = [];
        $after = [];

        foreach ($changes as $key => $value) {
            if (($refs[$key] ?? null) === $value) {
                continue;
            }

            $before[$key] = $refs[$key] ?? null;
            $after[$key] = $value;

            if ($value === null) {
                unset($refs[$key]);
            } else {
                $refs[$key] = $value;
            }
        }

        if ($after === []) {
            return false;
        }

        $row->forceFill(['provider_refs' => $refs])->save();
        $this->audit->record(new AuditEntry($event, $row, $before, $after));

        return true;
    }
}
