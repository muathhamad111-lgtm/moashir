<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Data\Entities\PrivacyRequestData;
use App\Enums\Audit\AuditEvent;
use App\Models\Entities\PrivacyRequest;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class CreatePrivacyRequest
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(User $actor, PrivacyRequestData $data): PrivacyRequest
    {
        return DB::transaction(function () use ($actor, $data): PrivacyRequest {
            $request = PrivacyRequest::query()->create([
                'entity_id' => $data->entityId,
                'kind' => $data->kind,
                'details' => $data->details,
                'created_by_user_id' => $actor->id,
            ]);

            $this->audit->record(new AuditEntry(
                AuditEvent::PrivacyRequestCreated,
                $request,
                [],
                ['entity_id' => $data->entityId, 'kind' => $data->kind->value],
                ['privacy'],
            ));

            return $request;
        });
    }
}
