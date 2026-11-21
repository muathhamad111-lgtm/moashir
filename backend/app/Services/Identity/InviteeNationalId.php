<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Exceptions\Identity\NationalIdRejected;
use App\Models\Identity\User;
use App\Support\ValueObjects\NationalId;

final readonly class InviteeNationalId
{
    public function __construct(private NationalIdRequirement $requirement, private NationalIdAssigner $assigner) {}

    public function record(User $invitee, ?NationalId $nationalId): void
    {
        if (! $this->requirement->isRequiredFor($invitee)) {
            return;
        }

        $this->assigner->assignFirst($invitee, $nationalId ?? throw NationalIdRejected::missing());
    }
}
