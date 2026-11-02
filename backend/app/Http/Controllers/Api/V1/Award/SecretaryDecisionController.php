<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\ApproveCommitteeAverage;
use App\Actions\Award\ApproveSubstitute;
use App\Actions\Award\RecomputeCommitteeAverage;
use App\Actions\Award\ReturnToCommittee;
use App\Actions\Award\ShowSecretaryRequest;
use App\Http\Requests\Award\CycleActionRequest;
use App\Http\Requests\Award\ReturnToCommitteeRequest;
use App\Http\Requests\Award\SecretaryNoteRequest;
use App\Http\Resources\Award\SecretaryRequestDetailResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class SecretaryDecisionController
{
    public function __construct(private ShowSecretaryRequest $show) {}

    public function recompute(CycleActionRequest $request, AwardCycle $cycle, Assessment $assessment, #[CurrentUser] User $actor, RecomputeCommitteeAverage $recompute): JsonResponse
    {
        $recorded = $recompute->handle($actor, $cycle, $assessment, $request->reason());

        return $this->detail($assessment, ['recomputed' => $recorded->isNew, 'committee_average_id' => $recorded->average->id]);
    }

    public function approve(SecretaryNoteRequest $request, AwardCycle $cycle, Assessment $assessment, #[CurrentUser] User $actor, ApproveCommitteeAverage $approve): JsonResponse
    {
        $approve->handle($actor, $cycle, $assessment, $request->note(), $request->reason());

        return $this->detail($assessment);
    }

    public function return(ReturnToCommitteeRequest $request, AwardCycle $cycle, Assessment $assessment, #[CurrentUser] User $actor, ReturnToCommittee $return): JsonResponse
    {
        $return->handle($actor, $cycle, $assessment, $request->generalNote(), $request->reason());

        return $this->detail($assessment);
    }

    public function approveSubstitution(SecretaryNoteRequest $request, AwardCycle $cycle, Assessment $assessment, CommitteeAssignment $substitution, #[CurrentUser] User $actor, ApproveSubstitute $approve): JsonResponse
    {
        $approve->handle($actor, $cycle, $assessment, $substitution, $request->note(), $request->reason());

        return $this->detail($assessment);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function detail(Assessment $assessment, array $meta = []): JsonResponse
    {
        $fresh = Assessment::query()->findOrFail($assessment->id);

        return ApiResponse::resource(new SecretaryRequestDetailResource($this->show->handle($fresh)), Response::HTTP_OK, $meta);
    }
}
