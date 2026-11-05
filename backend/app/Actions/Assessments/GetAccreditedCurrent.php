<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AccreditedCurrent;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Assessments\Assessment;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Assessments\SubmissionProgressReader;

final readonly class GetAccreditedCurrent
{
    public function __construct(private SubmissionProgressReader $progress) {}

    public function handle(User $actor): AccreditedCurrent
    {
        $entityId = (int) $actor->entity_id;
        $requests = Assessment::query()->with(['entity', 'currentResult'])->ownedBy($entityId)->ofPath(AssessmentPath::Accredited);
        $active = (clone $requests)->open()->latest('id')->first();

        return new AccreditedCurrent(
            Payment::query()->ownedBy($entityId)->where('purpose', PaymentPurpose::Accredited)->open()->latest('id')->first(),
            $active,
            (clone $requests)->whereNotIn('status', AssessmentPath::Accredited->openStateValues())->latest('id')->first(),
            $active instanceof Assessment ? $this->progress->read($active) : null,
        );
    }
}
