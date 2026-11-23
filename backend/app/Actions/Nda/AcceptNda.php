<?php

declare(strict_types=1);

namespace App\Actions\Nda;

use App\Exceptions\Nda\NdaRejected;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Services\Identity\TermsAcceptanceRecorder;
use App\Services\Nda\NdaAcceptanceCheck;

/**
 * A signed-in user accepts the current agreement version. Accepting it twice is harmless and answers the same.
 */
final readonly class AcceptNda
{
    public function __construct(private NdaAcceptanceCheck $check, private TermsAcceptanceRecorder $recorder) {}

    public function handle(User $user, int $termsVersionId): TermsVersion
    {
        $current = $this->check->current();

        if (! $current instanceof TermsVersion || $current->id !== $termsVersionId) {
            throw NdaRejected::versionNotCurrent();
        }

        $this->recorder->acceptNda($user, $current);

        return $current;
    }
}
