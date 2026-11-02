<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\RequestDetail;
use App\Enums\Finance\PaymentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentRecordScope;
use Illuminate\Database\Eloquent\Builder;

final readonly class ShowRequest
{
    public function __construct(private ShowAssessmentRecord $records, private AssessmentRecordScope $scope) {}

    public function handle(User $viewer, Assessment $assessment): RequestDetail
    {
        $assessment->loadMissing(['activeAssignment.assessor', 'openCompletionRequest', 'payment', 'awardCycle', 'region']);
        $revealsIdentities = $this->scope->accessOf($viewer, $assessment)->revealsStaffIdentities();
        $assignments = AssessmentAssignment::query()
            ->with(['assessor', 'assignedBy'])
            ->where('assessment_id', $assessment->id)
            ->when(! $revealsIdentities, static fn (Builder $query): Builder => $query->where('assessor_user_id', $viewer->id))
            ->orderBy('id')
            ->get()
            ->all();

        return new RequestDetail(
            $this->records->handle($viewer, $assessment),
            array_values($assignments),
            array_values(AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->orderBy('id')->get()->all()),
            $revealsIdentities,
            $assessment->payment?->status === PaymentStatus::Paid,
        );
    }
}
