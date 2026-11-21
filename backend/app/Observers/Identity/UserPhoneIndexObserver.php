<?php

declare(strict_types=1);

namespace App\Observers\Identity;

use App\Enums\Identity\BlindIndexPurpose;
use App\Models\Identity\User;
use App\Services\Identity\BlindIndex;

final readonly class UserPhoneIndexObserver
{
    public function __construct(private BlindIndex $blindIndex) {}

    public function saving(User $user): void
    {
        if (! $user->isDirty('phone')) {
            return;
        }

        $phone = $user->phone;
        $user->phone_index = $phone === null ? null : $this->blindIndex->of(BlindIndexPurpose::Phone, $phone);

        if (! $user->isDirty('phone_verified_at')) {
            $user->phone_verified_at = null;
        }
    }
}
