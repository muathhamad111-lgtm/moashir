<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\LoginOutcome;
use App\Enums\Identity\SignInMethod;
use App\Models\Identity\User;
use App\Services\Identity\SignInAccountResolver;
use App\Services\Identity\SignInGuard;
use App\Support\ValueObjects\SignInIdentifier;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Str;

final readonly class AttemptPasswordSignIn
{
    public function __construct(
        private SignInAccountResolver $accounts,
        private Hasher $hasher,
        private SignInGuard $guard,
        private RecordFailedSignIn $recordFailure,
        private CompleteSignIn $completeSignIn,
    ) {}

    public function handle(SignInIdentifier $identifier, string $password): LoginOutcome
    {
        $user = $this->accounts->resolve($identifier);

        $hashedPassword = $user?->password;

        if (! $user instanceof User || $hashedPassword === null) {
            $this->hasher->check($password, $this->hasher->make(Str::random(32)));
            $this->recordFailure->handle($identifier, null);
        }

        $this->guard->assertNotLocked($user);

        if (! $this->hasher->check($password, $hashedPassword)) {
            $this->recordFailure->handle($identifier, $user);
        }

        $this->guard->assertCanSignIn($user);

        return $this->completeSignIn->handle($user, SignInMethod::Password);
    }
}
