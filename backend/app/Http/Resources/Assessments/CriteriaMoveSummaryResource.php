<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\CriteriaMoveItem;
use App\Data\Assessments\CriteriaMoveSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CriteriaMoveSummary $resource
 */
final class CriteriaMoveSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = $this->resource;

        return [
            'from_version_id' => $summary->move->from_version_id,
            'from_version_number' => $summary->fromVersionNumber(),
            'to_version_id' => $summary->move->to_version_id,
            'to_version_number' => $summary->toVersionNumber(),
            'moved_at' => $summary->move->created_at->toIso8601String(),
            'scope' => $summary->move->scope->value,
            'trigger' => $summary->move->triggered_by->value,
            'kept' => $this->items($summary->kept),
            'reset' => $this->items($summary->reset),
            'added' => $this->items($summary->added),
        ];
    }

    /**
     * @param  list<CriteriaMoveItem>  $items
     * @return list<array{level: string, code: string, label: array{ar: string, en: string}|null}>
     */
    private function items(array $items): array
    {
        return array_map(static fn (CriteriaMoveItem $item): array => $item->toArray(), $items);
    }
}
