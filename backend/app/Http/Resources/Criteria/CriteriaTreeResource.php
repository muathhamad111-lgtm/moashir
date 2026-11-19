<?php

declare(strict_types=1);

namespace App\Http\Resources\Criteria;

use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Support\Criteria\ActiveWeights;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The whole tree of one version for the criteria workspace: active and inactive nodes, each in the same shape as
 * its own endpoint, so the workspace edits a node with exactly what the node endpoints return.
 *
 * @property-read CriteriaVersion $resource
 */
final class CriteriaTreeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->resource;

        return [
            'version' => new CriteriaVersionResource($version)->toArray($request),
            'active_weight_total' => ActiveWeights::total($version->mainCriteria)->toDecimal(),
            'mains' => $version->mainCriteria->map(fn (MainCriterion $main): array => $this->main($main, $request))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function main(MainCriterion $main, Request $request): array
    {
        return [
            ...new MainCriterionResource($main)->toArray($request),
            'subs' => $main->subCriteria->map(fn (SubCriterion $sub): array => $this->sub($sub, $request))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sub(SubCriterion $sub, Request $request): array
    {
        return [
            ...new SubCriterionResource($sub)->toArray($request),
            'points' => GuidingPointResource::collection($sub->guidingPoints)->resolve($request),
        ];
    }
}
