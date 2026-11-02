<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\EmailChangeStatus;
use App\Exceptions\Entities\EmailChangeRejected;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;

final readonly class EntityEmailChangeApplier
{
    public function __construct(private AuditRecorder $audit) {}

    public function apply(EmailChangeRequest $request, ?User $decidedBy): void
    {
        $owner = User::query()->whereKey($request->user_id)->lockForUpdate()->firstOrFail();
        $newEmail = new EmailAddress($request->new_email);

        if (User::query()->withTrashed()->withEmail($newEmail)->whereKeyNot($owner->id)->exists()) {
            throw EmailChangeRejected::addressTaken();
        }

        try {
            User::withoutAuditing(static fn (): bool => $owner->forceFill([
                'email' => $newEmail->value,
                'email_verified_at' => Date::now(),
            ])->save());
        } catch (UniqueConstraintViolationException) {
            throw EmailChangeRejected::addressTaken();
        }

        $request->forceFill([
            'status' => EmailChangeStatus::Applied,
            'applied_at' => Date::now(),
            'decided_by_user_id' => $decidedBy?->id,
        ])->save();

        $request->loadMissing('entity');

        if ($request->entity !== null) {
            $this->audit->record(new AuditEntry(
                AuditEvent::EntityEmailChangeApplied,
                $request->entity,
                ['email' => new EmailAddress($request->old_email)->masked()],
                ['email' => $newEmail->masked(), 'policy' => $request->policy->value],
            ));
        }
    }
}
