<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\EmailChangeStatus;
use App\Events\Entities\EntityEmailChangeRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Entities\Entity;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

final readonly class RejectEntityEmailChange
{
    public function __construct(private RequestContext $context, private AuditRecorder $audit) {}

    public function handle(User $actor, EmailChangeRequest $request, string $reason): EmailChangeRequest
    {
        $this->context->recordReason($reason);

        DB::transaction(function () use ($actor, $request, $reason): void {
            $locked = EmailChangeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isAwaitingApproval()) {
                throw IllegalStateTransition::from($locked->status->value, 'reject');
            }

            $request->forceFill([
                'status' => EmailChangeStatus::Rejected,
                'decided_by_user_id' => $actor->id,
                'decision_reason' => $reason,
            ])->save();

            if ($request->entity instanceof Entity) {
                $this->audit->record(new AuditEntry(
                    AuditEvent::EntityEmailChangeRejected,
                    $request->entity,
                    ['status' => EmailChangeStatus::AwaitingApproval->value],
                    ['status' => EmailChangeStatus::Rejected->value],
                ));
            }

            EntityEmailChangeRejected::dispatch($request);
        });

        return $request->refresh();
    }
}
