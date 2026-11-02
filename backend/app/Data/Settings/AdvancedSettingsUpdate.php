<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Enums\Settings\SettingKey;
use App\Support\ValueObjects\Percent;

final readonly class AdvancedSettingsUpdate
{
    public function __construct(
        public ?int $minEntityAgeYears = null,
        public ?Percent $disqualificationScore = null,
        public ?int $initialReviewSlaDays = null,
    ) {}

    /**
     * @return array<string, int|string|null>
     */
    public function values(): array
    {
        return [
            SettingKey::AdvancedMinEntityAgeYears->value => $this->minEntityAgeYears,
            SettingKey::AdvancedDisqualificationScore->value => $this->disqualificationScore?->toDisplay(),
            SettingKey::AdvancedInitialReviewSlaDays->value => $this->initialReviewSlaDays,
        ];
    }
}
