<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\Settings\PathSettingsView;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Settings\PathSetting;
use App\Services\Assessments\UploadLimitsReader;
use App\Services\Finance\FinanceSettings;
use App\Services\Finance\ServicePriceQuotes;
use App\Support\ScalarValue;
use App\Support\ValueObjects\PathEntityTexts;

final readonly class PathSettingsReader
{
    public function __construct(
        private SettingsStore $settings,
        private UploadLimitsReader $limits,
        private ServicePriceQuotes $quotes,
        private FinanceSettings $finance,
        private AssessmentSettingsReader $assessment,
    ) {}

    public function read(AssessmentPath $path): PathSettingsView
    {
        $purpose = self::purposeOf($path);

        return new PathSettingsView(
            $path,
            $this->reviewSlaDays($path),
            $this->assessment->slaLimits(),
            $this->limits->read($path),
            $purpose instanceof PaymentPurpose,
            $purpose instanceof PaymentPurpose ? $this->quotes->quoteOf($purpose, $this->finance->vatRate()) : null,
            $this->textsOf($path),
        );
    }

    public function textsOf(AssessmentPath $path): PathEntityTexts
    {
        return PathEntityTexts::fromStored($this->settings->get(PathSetting::EntityTexts->keyFor($path)));
    }

    public static function purposeOf(AssessmentPath $path): ?PaymentPurpose
    {
        return $path === AssessmentPath::Accredited ? PaymentPurpose::Accredited : null;
    }

    private function reviewSlaDays(AssessmentPath $path): int
    {
        $key = PathSetting::ReviewSlaDays->keyFor($path);

        return max(1, ScalarValue::int($this->settings->get($key), ScalarValue::int($key->defaultValue())));
    }
}
