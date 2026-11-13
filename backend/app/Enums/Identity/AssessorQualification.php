<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Enums\Assessments\AssessmentPath;
use App\Support\ValueObjects\LocalizedText;

enum AssessorQualification: string
{
    case Accredited = 'accredited';
    case AdvancedInitial = 'advanced_initial';

    public static function forPath(AssessmentPath $path): ?self
    {
        return match ($path) {
            AssessmentPath::Accredited => self::Accredited,
            AssessmentPath::Advanced => self::AdvancedInitial,
            AssessmentPath::SelfAssessment => null,
        };
    }

    public function path(): AssessmentPath
    {
        return match ($this) {
            self::Accredited => AssessmentPath::Accredited,
            self::AdvancedInitial => AssessmentPath::Advanced,
        };
    }

    public function label(): LocalizedText
    {
        return LocalizedText::fromTranslation('assessments.qualifications.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
