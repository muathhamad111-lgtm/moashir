<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\PrivacyRequestStatus;
use App\Exceptions\IllegalStateTransition;
use App\Models\Entities\PrivacyRequest;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class PrivacyRequestDecider
{
    public function __construct(private RequestContext $context, private AuditRecorder $audit) {}

    public function decide(User $actor, PrivacyRequest $request, PrivacyRequestStatus $outcome, ?string $note): PrivacyRequest
    {
        $this->context->recordReason($note);
        $event = $outcome === PrivacyRequestStatus::Fulfilled ? AuditEvent::PrivacyRequestFulfilled : AuditEvent::PrivacyRequestRejected;

        DB::transaction(function () use ($actor, $request, $outcome, $note, $event): void {
            $locked = PrivacyRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw IllegalStateTransition::from($locked->status->value, $outcome === PrivacyRequestStatus::Fulfilled ? 'fulfil' : 'reject');
            }

            $request->forceFill([
                'status' => $outcome,
                'decided_by_user_id' => $actor->id,
                'decision_note' => $note,
                'decided_at' => Date::now(),
            ])->save();

            $this->audit->record(new AuditEntry(
                $event,
                $request,
                ['status' => PrivacyRequestStatus::Open->value],
                ['status' => $outcome->value],
                ['privacy'],
            ));
        });

        return $request->refresh();
    }
}
