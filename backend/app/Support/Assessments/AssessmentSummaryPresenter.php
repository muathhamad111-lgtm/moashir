<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Models\Assessments\AssessmentSummary;

final class AssessmentSummaryPresenter
{
    /**
     * @return array{body: string, version: int, updated_at: string}|null
     */
    public static function present(?AssessmentSummary $summary): ?array
    {
        if (! $summary instanceof AssessmentSummary) {
            return null;
        }

        return [
            'body' => $summary->body,
            'version' => $summary->version,
            'updated_at' => $summary->created_at->toIso8601String(),
        ];
    }
}
