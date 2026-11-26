<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Assessments\CompletionItemKind;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;

final class CompletionScope
{
    /**
     * @param  string  $code  the main criterion or sub-criterion the change touches
     */
    public function assertRequested(Assessment $locked, string $code, CompletionItemKind $kind, string $field): void
    {
        if (! $this->isBackForCompletion($locked)) {
            return;
        }

        $request = $locked->loadMissing('openCompletionRequest')->openCompletionRequest;

        if (! $request instanceof AssessmentCompletionRequest || ! $request->requests($code, $kind->value)) {
            throw AssessmentRejected::completionItemNotRequested($field);
        }
    }

    /**
     * The open completion request while the request is back with the entity for completion; null otherwise.
     */
    public function openRequestOf(Assessment $assessment): ?AssessmentCompletionRequest
    {
        return $this->isBackForCompletion($assessment) ? $assessment->loadMissing('openCompletionRequest')->openCompletionRequest : null;
    }

    private function isBackForCompletion(Assessment $assessment): bool
    {
        return $assessment->path->isReviewed() && $assessment->status === $assessment->path->reviewStages()->needsCompletion;
    }
}
