<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Certificates\CertificateStatus;
use App\Models\Certificates\Certificate;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

final readonly class CertificateSupersession
{
    public function __construct(private AuditRecorder $audit) {}

    public function supersede(Certificate $source): void
    {
        if (! $source->isInForceOrSuspended()) {
            return;
        }

        $previous = $source->status;
        $source->forceFill(['status' => CertificateStatus::Superseded, 'superseded_at' => Date::now()])->save();

        $this->audit->record(new AuditEntry(AuditEvent::CertificateSuperseded, $source, ['status' => $previous->value], [
            'status' => CertificateStatus::Superseded->value,
            'number' => $source->number,
        ]));
    }
}
