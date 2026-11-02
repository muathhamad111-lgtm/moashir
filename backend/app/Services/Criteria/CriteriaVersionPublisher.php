<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Enums\Criteria\CriteriaVersionStatus;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;

final readonly class CriteriaVersionPublisher
{
    public function __construct(private CriteriaSettings $settings) {}

    public function publish(CriteriaVersion $draft, ?CriteriaVersion $previous, ?User $actor): void
    {
        $now = Date::now();

        $previous?->forceFill(['status' => CriteriaVersionStatus::Archived, 'archived_at' => $now])->save();

        $draft->forceFill([
            'status' => CriteriaVersionStatus::Published,
            'published_at' => $now,
            'published_by_user_id' => $actor?->id,
            'scale_kind' => $this->settings->scaleKind(),
            'validation_errors' => [],
        ])->save();
    }
}
