<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Support\ValueObjects\GapThresholds;
use App\Support\ValueObjects\MaturityBounds;

final readonly class InsightsSettingsUpdate
{
    public function __construct(
        public ?GapThresholds $gaps,
        public ?MaturityBounds $bands,
        public ?string $reason,
    ) {}

    public function isEmpty(): bool
    {
        return ! $this->gaps instanceof GapThresholds && ! $this->bands instanceof MaturityBounds;
    }
}
