<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Data\Finance\StaffBankTransferDetail;
use App\Support\Finance\LinkedRequestPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read StaffBankTransferDetail $resource
 */
final class BankTransferDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...new BankTransferResource($this->resource->transfer)->toArray($request),
            'linked_request' => LinkedRequestPresenter::of($this->resource->linkedRequest),
        ];
    }
}
