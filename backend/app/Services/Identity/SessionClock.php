<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Support\ScalarValue;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Date;

final readonly class SessionClock
{
    public function __construct(private Session $session) {}

    public function start(): void
    {
        $now = Date::now()->getTimestamp();

        $this->session->forget([
            SessionKeys::PENDING_USER_ID,
            SessionKeys::PENDING_STAGE,
            SessionKeys::PENDING_EXPIRES_AT,
            SessionKeys::PENDING_FAILURES,
        ]);
        $this->session->put([SessionKeys::SIGNED_IN_AT => $now, SessionKeys::LAST_SEEN_AT => $now]);
    }

    public function touch(): void
    {
        $this->session->put(SessionKeys::LAST_SEEN_AT, Date::now()->getTimestamp());
    }

    public function signedInAt(): int
    {
        return ScalarValue::int($this->session->get(SessionKeys::SIGNED_IN_AT));
    }

    public function lastSeenAt(): int
    {
        return ScalarValue::int($this->session->get(SessionKeys::LAST_SEEN_AT));
    }

    public function currentSessionId(): string
    {
        return $this->session->getId();
    }
}
