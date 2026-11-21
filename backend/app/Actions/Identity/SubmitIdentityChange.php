<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Data\Identity\IdentityChangeDraft;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\IdentityChangeStatus;
use App\Enums\Identity\IdentityField;
use App\Exceptions\Identity\IdentityChangeRejected;
use App\Models\Identity\IdentityChangeRequest;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\IdentityChangeNotifier;
use App\Services\Identity\NationalIdAssigner;
use App\Support\ValueObjects\NationalId;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class SubmitIdentityChange
{
    public function __construct(
        private NationalIdAssigner $nationalIds,
        private AuditRecorder $audit,
        private IdentityChangeNotifier $notifier,
    ) {}

    public function handle(User $submitter, User $subject, IdentityChangeDraft $draft): IdentityChangeRequest
    {
        try {
            $request = DB::transaction(fn (): IdentityChangeRequest => $this->open($submitter, $subject, $draft));
        } catch (UniqueConstraintViolationException) {
            throw IdentityChangeRejected::alreadyPending();
        }

        $this->notifier->toReviewers($request);

        return $request;
    }

    private function open(User $submitter, User $subject, IdentityChangeDraft $draft): IdentityChangeRequest
    {
        $locked = User::query()->whereKey($subject->id)->lockForUpdate()->firstOrFail();
        $this->assertChangesSomething($locked, $draft);

        if (IdentityChangeRequest::query()->where('user_id', $locked->id)->where('status', IdentityChangeStatus::Pending)->exists()) {
            throw IdentityChangeRejected::alreadyPending();
        }

        if ($draft->newNationalId instanceof NationalId) {
            $this->nationalIds->assertAvailable($draft->newNationalId, $locked);
        }

        $request = IdentityChangeRequest::query()->create([
            'user_id' => $locked->id,
            'submitted_by_user_id' => $submitter->id,
            'new_name' => $draft->newName,
            'new_national_id' => $draft->newNationalId?->value,
            'reason' => $draft->reason,
            'status' => IdentityChangeStatus::Pending,
        ]);
        $request->setRelation('user', $locked);

        $this->audit->record(new AuditEntry(AuditEvent::IdentityChangeRequested, $locked, [], [
            'request' => $request->public_id,
            'fields' => array_map(static fn (IdentityField $field): string => $field->value, $request->fields()),
            'on_behalf' => $submitter->id !== $locked->id,
        ]));

        return $request;
    }

    private function assertChangesSomething(User $subject, IdentityChangeDraft $draft): void
    {
        $nameChanges = $draft->newName !== null && $draft->newName !== $subject->name;
        $idChanges = $draft->newNationalId instanceof NationalId && $draft->newNationalId->value !== $subject->national_id;

        if (! $nameChanges && ! $idChanges) {
            throw IdentityChangeRejected::nothingToChange();
        }
    }
}
