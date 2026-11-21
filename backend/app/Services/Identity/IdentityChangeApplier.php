<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\IdentityField;
use App\Exceptions\Identity\NationalIdRejected;
use App\Models\Identity\IdentityChangeRequest;
use App\Models\Identity\User;
use App\Models\Identity\UserIdentityChange;
use App\Support\ValueObjects\NationalId;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class IdentityChangeApplier
{
    public function __construct(private NationalIdAssigner $nationalIds) {}

    public function apply(IdentityChangeRequest $request, User $user, User $approver): void
    {
        foreach ($request->fields() as $field) {
            $this->record($request, $user, $approver, $field);
        }

        $this->write($request, $user);
    }

    private function record(IdentityChangeRequest $request, User $user, User $approver, IdentityField $field): void
    {
        UserIdentityChange::query()->create([
            'user_id' => $user->id,
            'identity_change_request_id' => $request->id,
            'field' => $field,
            'old_value' => $field === IdentityField::Name ? $user->name : $user->national_id,
            'new_value' => $field === IdentityField::Name ? $request->new_name : $request->new_national_id,
            'requested_by_user_id' => $request->submitted_by_user_id,
            'approved_by_user_id' => $approver->id,
        ]);
    }

    private function write(IdentityChangeRequest $request, User $user): void
    {
        $changes = array_filter(['name' => $request->new_name, 'national_id' => $request->new_national_id], static fn (?string $value): bool => $value !== null);

        if ($request->new_national_id !== null) {
            $this->nationalIds->assertAvailable(new NationalId($request->new_national_id), $user);
        }

        try {
            User::withoutAuditing(static fn (): bool => $user->forceFill($changes)->save());
        } catch (UniqueConstraintViolationException) {
            throw NationalIdRejected::unavailable();
        }
    }
}
