<?php

declare(strict_types=1);

namespace App\Observers\Identity;

use App\Enums\Identity\BlindIndexPurpose;
use App\Models\Identity\User;
use App\Services\Identity\BlindIndex;

final readonly class UserNationalIdIndexObserver
{
    public function __construct(private BlindIndex $blindIndex) {}

    public function saving(User $user): void
    {
        if (! $user->isDirty('national_id')) {
            return;
        }

        $nationalId = $user->national_id;
        $user->national_id_index = $nationalId === null ? null : $this->blindIndex->of(BlindIndexPurpose::NationalId, $nationalId);
    }
}
