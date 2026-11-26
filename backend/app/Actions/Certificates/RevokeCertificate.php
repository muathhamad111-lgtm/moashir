<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Certificates\CertificateStatus;
use App\Events\Certificates\CertificateRevoked;
use App\Exceptions\Certificates\CertificateRejected;
use App\Models\Certificates\Certificate;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class RevokeCertificate
{
    public function __construct(private StaffActionReason $reasons, private AuditRecorder $audit) {}

    public function handle(User $actor, Certificate $certificate, string $reason): Certificate
    {
        $this->reasons->record($actor, $reason);

        return DB::transaction(function () use ($actor, $certificate, $reason): Certificate {
            $locked = Certificate::query()->whereKey($certificate->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isInForceOrSuspended()) {
                throw CertificateRejected::notIssued();
            }

            $previous = $locked->status;
            $locked->forceFill([
                'status' => CertificateStatus::Revoked,
                'revoked_at' => Date::now(),
                'revoked_reason' => $reason,
                'revoked_by_user_id' => $actor->id,
            ])->save();

            $this->audit->record(new AuditEntry(AuditEvent::CertificateRevoked, $locked, ['status' => $previous->value], [
                'status' => CertificateStatus::Revoked->value,
                'number' => $locked->number,
            ]));

            CertificateRevoked::dispatch($locked, $actor->id);

            return $locked;
        });
    }
}
