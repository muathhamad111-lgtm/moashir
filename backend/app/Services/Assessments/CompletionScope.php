<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Assessments\CompletionItemKind;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;

final class CompletionScope
{
    public function assertRequested(Assessment $locked, string $subCode, CompletionItemKind $kind, string $field): void
    {
        if (! $locked->path->isReviewed() || $locked->status !== $locked->path->reviewStages()->needsCompletion) {
            return;
        }

        $request = $locked->loadMissing('openCompletionRequest')->openCompletionRequest;

        if (! $request instanceof AssessmentCompletionRequest || ! $request->requests($subCode, $kind->value)) {
            throw AssessmentRejected::completionItemNotRequested($field);
        }
    }
}
