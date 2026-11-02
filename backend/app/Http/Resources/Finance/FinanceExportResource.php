<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Models\Finance\FinanceExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read FinanceExport $resource
 */
final class FinanceExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $export = $this->resource;

        return [
            'id' => $export->public_id,
            'status' => $export->status->value,
            'row_count' => $export->row_count,
            'created_at' => $export->created_at->toIso8601String(),
            'completed_at' => $export->completed_at?->toIso8601String(),
        ];
    }
}
