<?php

declare(strict_types=1);

namespace App\Services\Nda;

use App\Enums\Identity\TermsKind;
use App\Enums\Nda\NdaUserStatus;
use App\Models\Identity\TermsAcceptance;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Services\Identity\CurrentTermsVersions;

/**
 * Whether a user must still accept the current agreement version before using the platform.
 */
final readonly class NdaAcceptanceCheck
{
    public function __construct(private NdaScope $scope, private CurrentTermsVersions $terms) {}

    public function current(): ?TermsVersion
    {
        return $this->terms->of(TermsKind::Nda);
    }

    public function isRequiredFor(User $user): bool
    {
        return $this->statusOf($user) === NdaUserStatus::Pending;
    }

    public function statusOf(User $user): NdaUserStatus
    {
        $current = $this->current();

        if (! $current instanceof TermsVersion || ! $this->scope->covers($user)) {
            return NdaUserStatus::OutOfScope;
        }

        return $this->hasAccepted($user, $current) ? NdaUserStatus::Accepted : NdaUserStatus::Pending;
    }

    public function hasAccepted(User $user, TermsVersion $version): bool
    {
        return TermsAcceptance::query()->where('user_id', $user->id)->where('terms_version_id', $version->id)->exists();
    }
}
