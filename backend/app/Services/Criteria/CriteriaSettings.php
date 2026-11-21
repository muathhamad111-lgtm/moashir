<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Enums\Criteria\ScaleKind;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsStore;
use App\Support\ScalarValue;
use Illuminate\Contracts\Config\Repository;

/**
 * The assessment scale a criteria version is stamped with when it is published. The scale chosen in the
 * settings hub wins; until it is set, the deployment default (`ASSESSMENT_SCALE_KIND`) applies.
 */
final readonly class CriteriaSettings
{
    public function __construct(private SettingsStore $settings, private Repository $config) {}

    public function scaleKind(): ScaleKind
    {
        return $this->chosenScaleKind() ?? $this->deploymentScaleKind() ?? ScaleKind::OutOfTen;
    }

    public function chosenScaleKind(): ?ScaleKind
    {
        return ScaleKind::tryFrom(ScalarValue::string($this->settings->get(SettingKey::AssessmentScaleKind)));
    }

    private function deploymentScaleKind(): ?ScaleKind
    {
        return ScaleKind::tryFrom(ScalarValue::string($this->config->get('moashir.assessment.scale_kind')));
    }
}
