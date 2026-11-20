<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\IdentityChangeStatus;
use App\Exceptions\Identity\IdentityChangeRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Identity\IdentityChangeRequest;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\IdentityChangeNotifier;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class RejectIdentityChange
{
    public function __construct(private AuditRecorder $audit, private IdentityChangeNotifier $notifier) {}

    public function handle(User $reviewer, IdentityChangeRequest $request, string $note): IdentityChangeRequest
    {
        if ($request->involves($reviewer->id)) {
            throw IdentityChangeRejected::ownRequest();
        }

        DB::transaction(function () use ($reviewer, $request, $note): void {
            $locked = IdentityChangeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw IllegalStateTransition::from($locked->status->value, 'reject');
            }

            $locked->forceFill([
                'status' => IdentityChangeStatus::Rejected,
                'reviewed_by_user_id' => $reviewer->id,
                'review_note' => $note,
                'decided_at' => Date::now(),
            ])->save();

            $this->audit->record(new AuditEntry(AuditEvent::IdentityChangeRejected, $locked->user, [], ['request' => $locked->public_id]));
        });

        $this->notifier->toRequester($request->refresh());

        return $request;
    }
}
