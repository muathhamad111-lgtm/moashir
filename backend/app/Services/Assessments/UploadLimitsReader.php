<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\UploadLimits;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Files\EvidenceFileType;
use App\Enums\Settings\PathSetting;
use App\Services\Settings\SettingsStore;
use App\Support\ScalarValue;

final readonly class UploadLimitsReader
{
    public function __construct(private SettingsStore $settings) {}

    public function read(AssessmentPath $path): UploadLimits
    {
        $maxFile = PathSetting::UploadMaxFileMb->keyFor($path);
        $types = PathSetting::EvidenceAllowedTypes->keyFor($path);
        $perSub = PathSetting::EvidenceMaxFilesPerSub->keyFor($path);
        $values = $this->settings->many([$maxFile, $types, $perSub]);

        return new UploadLimits(
            max(1, ScalarValue::int($values[$maxFile->value] ?? null, ScalarValue::int($maxFile->defaultValue()))),
            $this->typesFrom($values[$types->value] ?? null),
            max(1, ScalarValue::int($values[$perSub->value] ?? null, ScalarValue::int($perSub->defaultValue()))),
        );
    }

    /**
     * @return list<EvidenceFileType>
     */
    private function typesFrom(mixed $stored): array
    {
        $types = [];

        foreach (is_array($stored) ? $stored : [] as $value) {
            $type = is_string($value) ? EvidenceFileType::tryFrom($value) : null;

            if ($type instanceof EvidenceFileType) {
                $types[] = $type;
            }
        }

        return $types;
    }
}
