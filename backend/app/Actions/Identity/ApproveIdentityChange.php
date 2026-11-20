<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\IdentityChangeStatus;
use App\Enums\Identity\IdentityField;
use App\Exceptions\Identity\IdentityChangeRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Identity\IdentityChangeRequest;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\IdentityChangeApplier;
use App\Services\Identity\IdentityChangeNotifier;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ApproveIdentityChange
{
    public function __construct(
        private IdentityChangeApplier $applier,
        private AuditRecorder $audit,
        private IdentityChangeNotifier $notifier,
    ) {}

    public function handle(User $reviewer, IdentityChangeRequest $request, ?string $note): IdentityChangeRequest
    {
        if ($request->involves($reviewer->id)) {
            throw IdentityChangeRejected::ownRequest();
        }

        DB::transaction(function () use ($reviewer, $request, $note): void {
            $locked = IdentityChangeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw IllegalStateTransition::from($locked->status->value, 'approve');
            }

            $user = User::withTrashed()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
            $this->applier->apply($locked, $user, $reviewer);
            $locked->forceFill([
                'status' => IdentityChangeStatus::Approved,
                'reviewed_by_user_id' => $reviewer->id,
                'review_note' => $note,
                'decided_at' => Date::now(),
            ])->save();

            $this->audit->record(new AuditEntry(AuditEvent::IdentityChangeApproved, $user, [], [
                'request' => $locked->public_id,
                'fields' => array_map(static fn (IdentityField $field): string => $field->value, $locked->fields()),
            ]));
        });

        $this->notifier->toRequester($request->refresh());

        return $request;
    }
}
