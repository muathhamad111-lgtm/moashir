<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\PendingAuthenticationStage;
use App\Exceptions\Identity\MfaRejected;
use App\Models\Identity\User;
use App\Support\ScalarValue;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Date;

final readonly class PendingAuthentication
{
    public const int MAX_FAILURES = 5;

    public function __construct(private Session $session, private AuthSettings $settings) {}

    public function begin(User $user, PendingAuthenticationStage $stage): void
    {
        $this->session->migrate(true);
        $this->session->put([
            SessionKeys::PENDING_USER_ID => $user->id,
            SessionKeys::PENDING_STAGE => $stage->value,
            SessionKeys::PENDING_EXPIRES_AT => Date::now()->addMinutes($this->settings->pendingAuthenticationTtlMinutes())->getTimestamp(),
            SessionKeys::PENDING_FAILURES => 0,
        ]);
    }

    public function userFor(PendingAuthenticationStage $stage): User
    {
        $userId = $this->session->get(SessionKeys::PENDING_USER_ID);
        $isCurrentStage = $this->session->get(SessionKeys::PENDING_STAGE) === $stage->value;
        $expiresAt = ScalarValue::int($this->session->get(SessionKeys::PENDING_EXPIRES_AT));

        if (! is_int($userId) || ! $isCurrentStage || $expiresAt < Date::now()->getTimestamp()) {
            throw MfaRejected::nothingPending();
        }

        return User::query()->findOrFail($userId);
    }

    public function isPending(PendingAuthenticationStage $stage): bool
    {
        return $this->session->get(SessionKeys::PENDING_STAGE) === $stage->value;
    }

    public function recordFailure(): int
    {
        $failures = ScalarValue::int($this->session->get(SessionKeys::PENDING_FAILURES)) + 1;
        $this->session->put(SessionKeys::PENDING_FAILURES, $failures);

        return $failures;
    }

    public function clear(): void
    {
        $this->session->forget([
            SessionKeys::PENDING_USER_ID,
            SessionKeys::PENDING_STAGE,
            SessionKeys::PENDING_EXPIRES_AT,
            SessionKeys::PENDING_FAILURES,
        ]);
    }
}
