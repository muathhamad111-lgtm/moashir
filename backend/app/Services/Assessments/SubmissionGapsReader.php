<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\BoundSubCriteria;
use App\Data\Assessments\MissingAttachment;
use App\Data\Assessments\MissingMainAttachment;
use App\Data\Assessments\SubmissionCoverage;
use App\Data\Assessments\SubmissionGaps;
use App\Enums\Assessments\CompletionItemKind;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;

/**
 * The CV entries and required attachments a request still lacks. While the request is back for completion, only the
 * requested items count: the entity cannot change anything else, so nothing else may block the resubmission.
 */
final readonly class SubmissionGapsReader
{
    public function __construct(private SubmissionCoverageReader $coverage, private CompletionScope $completion) {}

    public function read(Assessment $assessment, BoundSubCriteria $subs): SubmissionGaps
    {
        $coverage = $this->coverage->read($assessment);
        $request = $this->completion->openRequestOf($assessment);
        $missingCv = [];
        $missingAttachments = [];

        foreach ($subs->subs as $sub) {
            if ($coverage->lacksCv($sub) && $this->counts($request, $sub->code, CompletionItemKind::Cv)) {
                $missingCv[] = $sub;
            }

            foreach ($this->counts($request, $sub->code, CompletionItemKind::Evidence) ? $coverage->missingSlotKeysOf($sub) : [] as $key) {
                $missingAttachments[] = new MissingAttachment($sub->id, $sub->code, $key);
            }
        }

        return new SubmissionGaps($missingCv, $missingAttachments, $this->missingOfMains($subs, $coverage, $request));
    }

    /**
     * @return list<MissingMainAttachment>
     */
    private function missingOfMains(BoundSubCriteria $subs, SubmissionCoverage $coverage, ?AssessmentCompletionRequest $request): array
    {
        $missing = [];

        foreach ($subs->mains as $main) {
            foreach ($this->counts($request, $main->code, CompletionItemKind::Evidence) ? $coverage->missingSlotKeysOf($main) : [] as $key) {
                $missing[] = new MissingMainAttachment($main->id, $main->code, $key);
            }
        }

        return $missing;
    }

    private function counts(?AssessmentCompletionRequest $request, string $code, CompletionItemKind $kind): bool
    {
        return ! $request instanceof AssessmentCompletionRequest || $request->requests($code, $kind->value);
    }
}
