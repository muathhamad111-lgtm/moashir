<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\Assessment;

final class AssessmentLinks
{
    private const string STAFF_REQUESTS = '/admin/requests/';

    public static function selfForm(): string
    {
        return '/assessment/self';
    }

    public static function selfResult(int $assessmentId): string
    {
        return '/assessment/self/result/'.$assessmentId;
    }

    public static function accreditedForm(): string
    {
        return '/assessment/accredited';
    }

    public static function accreditedResult(int $assessmentId): string
    {
        return '/assessment/accredited/result/'.$assessmentId;
    }

    public static function accreditedFeedback(int $assessmentId): string
    {
        return '/assessment/accredited/result/'.$assessmentId.'/feedback';
    }

    public static function advancedForm(): string
    {
        return '/assessment/advanced';
    }

    public static function entityFormOf(Assessment $assessment): string
    {
        return match ($assessment->path) {
            AssessmentPath::Accredited => self::accreditedForm(),
            AssessmentPath::Advanced => self::advancedForm(),
            AssessmentPath::SelfAssessment => self::selfForm(),
        };
    }

    public static function request(int $assessmentId): string
    {
        return self::STAFF_REQUESTS.$assessmentId;
    }

    public static function review(int $assessmentId): string
    {
        return self::STAFF_REQUESTS.$assessmentId.'/review';
    }

    public static function evidenceBundle(int $assessmentId, string $bundleId): string
    {
        return self::STAFF_REQUESTS.$assessmentId.'/evidence-bundles/'.$bundleId;
    }
}
