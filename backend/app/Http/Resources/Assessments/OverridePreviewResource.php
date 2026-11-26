<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\OverridePreview;
use App\Data\Assessments\PreviewedResult;
use App\Data\Assessments\PreviewedScoreChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read OverridePreview $resource
 */
final class OverridePreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'before' => $this->result($this->resource->before),
            'after' => $this->result($this->resource->after),
            'changes' => array_map(static fn (PreviewedScoreChange $change): array => [
                'code' => $change->unit->code,
                'field' => $change->unit->inputName(),
                'before' => $change->before?->toDecimal(),
                'after' => $change->after?->toDecimal(),
            ], $this->resource->changes),
        ];
    }

    /**
     * @return array{percent: string, level: string|null}
     */
    private function result(PreviewedResult $result): array
    {
        return ['percent' => $result->percent->toDisplay(), 'level' => $result->level?->value];
    }
}
