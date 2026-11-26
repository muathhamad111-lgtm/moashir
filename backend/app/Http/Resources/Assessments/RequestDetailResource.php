<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\RequestDetail;
use App\Http\Resources\Entities\EntityLogoResource;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Support\Assessments\CompletionRequestPresenter;
use App\Support\Assessments\RequestPaymentPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @property-read RequestDetail $resource
 */
final class RequestDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detail = $this->resource;
        $assessment = $detail->record->assessment;

        return [
            ...new RequestQueueItemResource($assessment)->toArray($request),
            'entity' => [...RequestQueueItemResource::entityOf($assessment), 'logo' => $this->logoOf($assessment->entity, $request)],
            'has_certificates' => $detail->hasCertificates,
            'payment' => $this->paymentOf($detail, $request),
            'record' => new AssessmentRecordResource($detail->record)->toArray($request),
            'assignment_history' => array_map(fn (AssessmentAssignment $assignment): array => $this->assignmentOf($assignment, $detail->revealsStaffIdentities), $detail->assignments),
            'completion_requests' => array_map(
                static fn (AssessmentCompletionRequest $completion): ?array => CompletionRequestPresenter::present($completion, $detail->subs),
                $detail->completionRequests,
            ),
        ];
    }

    /**
     * The entity's logo for the record header: only for a viewer who may open it (the same rule as its download link).
     *
     * @return array{id: string, url: string|null, uploaded_at: string}|null
     */
    private function logoOf(Entity $entity, Request $request): ?array
    {
        $viewer = $request->user();

        if (! $entity->logo instanceof StoredFile || ! $viewer instanceof User || Gate::forUser($viewer)->denies('viewLogo', $entity)) {
            return null;
        }

        return new EntityLogoResource($entity->logo)->toArray($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentOf(RequestDetail $detail, Request $request): array
    {
        $paid = ['paid' => $detail->isPaid];

        if (! $detail->revealsStaffIdentities || ! RequestPaymentPresenter::revealsDetailsTo($request->user())) {
            return $paid;
        }

        return [...$paid, ...(RequestPaymentPresenter::of($detail->record->assessment->payment, $request->user()) ?? [])];
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentOf(AssessmentAssignment $assignment, bool $revealsIdentities): array
    {
        $item = [
            'id' => $assignment->id,
            'assessor' => ['id' => $assignment->assessor_user_id, 'name' => $assignment->assessor->name],
            'status' => $assignment->status->value,
            'assigned_at' => $assignment->created_at->toIso8601String(),
            'started_at' => $assignment->started_at?->toIso8601String(),
            'ended_at' => $assignment->ended_at?->toIso8601String(),
        ];

        if (! $revealsIdentities) {
            return $item;
        }

        return [
            ...$item,
            'assigned_by' => $assignment->assignedBy === null ? null : ['id' => $assignment->assignedBy->id, 'name' => $assignment->assignedBy->isSuperAdmin() ? null : $assignment->assignedBy->name],
            'reason' => $assignment->reason,
            'end_reason' => $assignment->end_reason,
        ];
    }
}
