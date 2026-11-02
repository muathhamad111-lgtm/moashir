<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Contracts\Assessments\ReviewedAssessmentState;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Notifications\NotificationCode;
use App\Events\Files\StoredFileQuarantined;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceFile;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;

final readonly class NotifyEntityOfRefusedEvidence
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(StoredFileQuarantined $event): void
    {
        if ($event->file->purpose !== StoredFilePurpose::AssessmentEvidence) {
            return;
        }

        $evidence = EvidenceFile::query()->with('assessment.entity')->where('stored_file_id', $event->file->id)->present()->first();

        if (! $evidence instanceof EvidenceFile || ! $this->concernsEntity($evidence->assessment)) {
            return;
        }

        $code = NotificationCode::EvidenceRefused;

        $this->notifier->toOwner($evidence->assessment->entity, RequestNotices::variant(
            $code,
            $code->value.'.'.($this->acceptsReplacement($evidence->assessment) ? 'replace' : 'locked'),
            $evidence->assessment,
            AssessmentLinks::entityFormOf($evidence->assessment),
            ['code' => $evidence->sub_code],
        )->inAppOnly());
    }

    private function concernsEntity(Assessment $assessment): bool
    {
        return $assessment->isOpen() && ! $assessment->isFrozen();
    }

    private function acceptsReplacement(Assessment $assessment): bool
    {
        return $assessment->status instanceof ReviewedAssessmentState && $assessment->status->acceptsEntityContent();
    }
}
