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
            'items' => self::items($request, $subs),
            'deadline_at' => $request->deadline_at->toIso8601String(),
            'expiry_outcome' => $request->expiry_outcome->value,
            'requested_at' => $request->created_at->toIso8601String(),
            'resolved_at' => $request->resolved_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function items(AssessmentCompletionRequest $request, ?BoundSubCriteria $subs): array
    {
        return array_values(array_map(static fn (array $item): array => [
            'main_criterion_id' => isset($item['main_code']) ? $subs?->mainWithCode($item['main_code'])?->id : null,
            'sub_criterion_id' => isset($item['sub_code']) ? $subs?->idOf($item['sub_code']) : null,
            'code' => AssessmentCompletionRequest::codeOf($item),
            'name' => $subs?->nameOf(AssessmentCompletionRequest::codeOf($item)),
            'kind' => $item['kind'],
            'note' => $item['note'],
        ], $request->items));
    }
}
