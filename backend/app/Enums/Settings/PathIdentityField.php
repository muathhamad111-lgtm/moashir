<?php

declare(strict_types=1);

namespace App\Enums\Settings;

use App\Enums\Assessments\AssessmentPath;

/**
 * The editable identity text of an assessment path. The path name is fixed by the translations
 * (`assessments.paths.*`) by operator decision, so only the description is stored as a setting.
 */
enum PathIdentityField: string
{
    case Description = 'description';

    public function maxLength(): int
    {
        return match ($this) {
            self::Description => 500,
        };
    }

    public function keyFor(AssessmentPath $path): SettingKey
    {
        return match ($this) {
            self::Description => match ($path) {
                AssessmentPath::SelfAssessment => SettingKey::SelfPathDescription,
                AssessmentPath::Accredited => SettingKey::AccreditedPathDescription,
                AssessmentPath::Advanced => SettingKey::AdvancedPathDescription,
            },
        };
    }

    /**
     * @return list<SettingKey>
     */
    public static function allKeys(): array
    {
        $keys = [];

        foreach (AssessmentPath::cases() as $path) {
            foreach (self::cases() as $field) {
                $keys[] = $field->keyFor($path);
            }
        }

        return $keys;
    }
}
