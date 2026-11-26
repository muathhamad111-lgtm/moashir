<?php

declare(strict_types=1);

namespace App\Support\Paths;

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Certificates\Certificate;

final class RequestTabs
{
    public const string REQUEST = 'request';

    public const string RESULT = 'result';

    public const string FEEDBACK = 'feedback';

    public const string CERTIFICATE = 'certificate';

    public const string TOOLS = 'tools';

    /**
     * @return array{available_tabs: list<string>|null, default_tab: string|null}
     */
    public static function of(Assessment $assessment): array
    {
        if (! $assessment->isAccredited()) {
            return ['available_tabs' => null, 'default_tab' => null];
        }

        $completed = $assessment->status === AccreditedAssessmentStatus::Completed;
        $tabs = [self::REQUEST];

        if ($completed) {
            $tabs = [...$tabs, self::RESULT, self::FEEDBACK];
        }

        if ($assessment->certificate instanceof Certificate) {
            $tabs[] = self::CERTIFICATE;
        }

        if ($completed) {
            $tabs[] = self::TOOLS;
        }

        return ['available_tabs' => $tabs, 'default_tab' => $completed ? self::RESULT : self::REQUEST];
    }
}
