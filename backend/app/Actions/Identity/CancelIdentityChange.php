<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\IdentityChangeStatus;
use App\Exceptions\IllegalStateTransition;
use App\Models\Identity\IdentityChangeRequest;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class CancelIdentityChange
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(IdentityChangeRequest $request): IdentityChangeRequest
    {
        DB::transaction(function () use ($request): void {
            $locked = IdentityChangeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw IllegalStateTransition::from($locked->status->value, 'cancel');
            }

            $locked->forceFill(['status' => IdentityChangeStatus::Cancelled, 'decided_at' => Date::now()])->save();
            $this->audit->record(new AuditEntry(AuditEvent::IdentityChangeCancelled, $locked->user, [], ['request' => $locked->public_id]));
        });

        return $request->refresh();
    }
}
