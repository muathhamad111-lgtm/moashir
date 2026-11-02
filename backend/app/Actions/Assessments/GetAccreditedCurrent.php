<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AccreditedCurrent;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Assessments\Assessment;
use App\Models\Finance\Payment;
use App\Models\Identity\User;

final class GetAccreditedCurrent
{
    public function handle(User $actor): AccreditedCurrent
    {
        $entityId = (int) $actor->entity_id;
        $requests = Assessment::query()->with(['entity', 'currentResult'])->ownedBy($entityId)->ofPath(AssessmentPath::Accredited);

        return new AccreditedCurrent(
            Payment::query()->ownedBy($entityId)->where('purpose', PaymentPurpose::Accredited)->open()->latest('id')->first(),
            (clone $requests)->open()->latest('id')->first(),
            (clone $requests)->whereNotIn('status', AssessmentPath::Accredited->openStateValues())->latest('id')->first(),
        );
    }
}
