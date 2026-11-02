<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Models\Identity\User;
use App\Models\Identity\UserSession;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class FindOwnSession
{
    public function handle(User $user, string $publicId): UserSession
    {
        $session = $user->sessions()->get()->first(
            static fn (UserSession $candidate): bool => hash_equals($candidate->publicId(), $publicId),
        );

        return $session ?? throw (new ModelNotFoundException)->setModel(UserSession::class);
    }
}
