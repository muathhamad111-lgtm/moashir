<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\Settings\PathSettingsView;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Settings\PathSetting;
use App\Services\Assessments\UploadLimitsReader;
use App\Services\Paths\PathPricingReader;
use App\Support\ScalarValue;
use App\Support\ValueObjects\PathEntityTexts;

final readonly class PathSettingsReader
{
    public function __construct(
        private SettingsStore $settings,
        private UploadLimitsReader $limits,
        private PathPricingReader $pricing,
        private AssessmentSettingsReader $assessment,
    ) {}

    public function read(AssessmentPath $path): PathSettingsView
    {
        $pricing = $path === AssessmentPath::Advanced ? $this->pricing->currentAward() : $this->pricing->accredited();

        return new PathSettingsView(
            $path,
            $this->reviewSlaDays($path),
            $this->assessment->slaLimits(),
            $this->limits->read($path),
            $pricing->requiresPayment(),
            $pricing->price,
            $this->textsOf($path),
        );
    }

    public function textsOf(AssessmentPath $path): PathEntityTexts
    {
        return PathEntityTexts::fromStored($this->settings->get(PathSetting::EntityTexts->keyFor($path)));
    }

    private function reviewSlaDays(AssessmentPath $path): int
    {
        $key = PathSetting::ReviewSlaDays->keyFor($path);

        return max(1, ScalarValue::int($this->settings->get($key), ScalarValue::int($key->defaultValue())));
    }
}
