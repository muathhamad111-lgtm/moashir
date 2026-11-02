<?php

declare(strict_types=1);

namespace App\Services\Identity;

final class SessionKeys
{
    public const string SIGNED_IN_AT = 'moashir.signed_in_at';

    public const string LAST_SEEN_AT = 'moashir.last_seen_at';

    public const string PENDING_USER_ID = 'moashir.pending.user_id';

    public const string PENDING_STAGE = 'moashir.pending.stage';

    public const string PENDING_EXPIRES_AT = 'moashir.pending.expires_at';

    public const string PENDING_FAILURES = 'moashir.pending.failures';
}
