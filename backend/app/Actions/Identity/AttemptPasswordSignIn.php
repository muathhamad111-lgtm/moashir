<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\LoginOutcome;
use App\Exceptions\Identity\AuthenticationRejected;
use App\Models\Identity\User;
use App\Services\Identity\AuthSettings;
use App\Services\Identity\SignInGuard;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Str;

final readonly class AttemptPasswordSignIn
{
    public function __construct(
        private AuthSettings $settings,
        private Hasher $hasher,
        private SignInGuard $guard,
        private RecordFailedSignIn $recordFailure,
        private CompleteSignIn $completeSignIn,
    ) {}

    public function handle(EmailAddress $email, string $password): LoginOutcome
    {
        if (! $this->settings->isEmailPasswordEnabled()) {
            throw AuthenticationRejected::modeDisabled();
        }

        $user = User::query()->withEmail($email)->first();

        $hashedPassword = $user?->password;

        if ($user === null || $hashedPassword === null) {
            $this->hasher->check($password, $this->hasher->make(Str::random(32)));
            $this->recordFailure->handle($email, null);
        }

        $this->guard->assertNotLocked($user);

        if (! $this->hasher->check($password, $hashedPassword)) {
            $this->recordFailure->handle($email, $user);
        }

        $this->guard->assertCanSignIn($user);

        return $this->completeSignIn->handle($user, 'password');
    }
}
