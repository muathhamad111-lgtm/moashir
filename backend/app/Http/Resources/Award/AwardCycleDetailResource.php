<?php

declare(strict_types=1);

namespace App\Http\Resources\Award;

use App\Data\Award\CycleView;
use App\Models\Award\AwardCycleAssessor;
use App\Models\Award\AwardCycleMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CycleView $resource
 */
final class AwardCycleDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = $this->resource;
        $cycle = $view->cycle;
        $detail = [...new AwardCycleResource($cycle)->toArray($request), 'can_manage' => $view->canManage];

        if (! $view->revealsStaff) {
            return $detail;
        }

        return [
            ...$detail,
            'members' => array_values($cycle->members->map(static fn (AwardCycleMember $member): array => [
                'id' => $member->user_id,
                'name' => $member->user->name,
                'role' => $member->role->value,
            ])->all()),
            'initial_assessors' => array_values($cycle->initialAssessors->map(static fn (AwardCycleAssessor $link): array => [
                'id' => $link->assessor_user_id,
                'name' => $link->assessor->name,
                'is_active' => $link->is_active,
            ])->all()),
        ];
    }
}
