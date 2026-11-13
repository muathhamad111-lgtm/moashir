<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\Settings\AssessmentSettingsView;
use App\Enums\Assessments\AssessmentPath;
use App\Services\Assessments\AccreditedReviewSla;
use App\Services\Assessments\UploadLimitsReader;
use App\Services\Award\CommitteeVisibilityReader;
use App\Services\Award\CycleDefaultsReader;
use App\Services\Files\FileSettings;
use App\Support\ScalarValue;
use Illuminate\Contracts\Config\Repository;

final readonly class AssessmentSettingsReader
{
    public function __construct(
        private AccreditedReviewSla $sla,
        private UploadLimitsReader $limits,
        private CycleDefaultsReader $cycleDefaults,
        private CommitteeVisibilityReader $committeeVisibility,
        private FileSettings $files,
        private Repository $config,
    ) {}

    public function read(): AssessmentSettingsView
    {
        return new AssessmentSettingsView(
            $this->sla->days(),
            $this->slaLimits(),
            $this->limits->read(AssessmentPath::Accredited),
            $this->files->maxUploadMegabytes(),
            $this->cycleDefaults->read(),
            $this->committeeVisibility->read(),
        );
    }

    /**
     * @return array{min: int, max: int}
     */
    public function slaLimits(): array
    {
        return [
            'min' => ScalarValue::int($this->config->get('moashir.assessment.sla_days_limits.min'), 1),
            'max' => ScalarValue::int($this->config->get('moashir.assessment.sla_days_limits.max'), 120),
        ];
    }
}
