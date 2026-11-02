<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Enums\Entities\PrivacyRequestStatus;
use App\Models\Entities\PrivacyRequest;
use App\Models\Identity\User;
use App\Services\Entities\PrivacyRequestDecider;

final readonly class RejectPrivacyRequest
{
    public function __construct(private PrivacyRequestDecider $decider) {}

    public function handle(User $actor, PrivacyRequest $request, string $reason): PrivacyRequest
    {
        return $this->decider->decide($actor, $request, PrivacyRequestStatus::Rejected, $reason);
    }
}
