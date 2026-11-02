<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Data\Assessments\BoundSubCriteria;
use App\Models\Assessments\AssessmentCompletionRequest;

final class CompletionRequestPresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function present(?AssessmentCompletionRequest $request, ?BoundSubCriteria $subs): ?array
    {
        if (! $request instanceof AssessmentCompletionRequest) {
            return null;
        }

        return [
            'id' => $request->id,
            'status' => $request->status->value,
            'notes' => $request->notes,
            'items' => array_map(static fn (array $item): array => [
                'sub_criterion_id' => $subs?->idOf($item['sub_code']),
                'code' => $item['sub_code'],
                'kind' => $item['kind'],
                'note' => $item['note'],
            ], $request->items),
            'deadline_at' => $request->deadline_at->toIso8601String(),
            'expiry_outcome' => $request->expiry_outcome->value,
            'requested_at' => $request->created_at->toIso8601String(),
            'resolved_at' => $request->resolved_at?->toIso8601String(),
        ];
    }
}
