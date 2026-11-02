<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Enums\Criteria\CriteriaNodeType;
use App\Models\Criteria\AttachmentRequirement;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use Illuminate\Database\Eloquent\Builder;

final class CriteriaNodeLocator
{
    public function inVersion(CriteriaVersion $version, CriteriaNodeType $type, int $id): MainCriterion|SubCriterion|GuidingPoint|AttachmentRequirement|null
    {
        $belongs = static fn (Builder $query): Builder => $query->where('criteria_version_id', $version->id);

        return match ($type) {
            CriteriaNodeType::Main => MainCriterion::query()->where('criteria_version_id', $version->id)->find($id),
            CriteriaNodeType::Sub => SubCriterion::query()->whereHas('mainCriterion', $belongs)->find($id),
            CriteriaNodeType::Point => GuidingPoint::query()->whereHas('subCriterion.mainCriterion', $belongs)->find($id),
            CriteriaNodeType::Attachment => AttachmentRequirement::query()
                ->where(static fn (Builder $query): Builder => $query
                    ->whereHas('subCriterion.mainCriterion', $belongs)
                    ->orWhereHas('guidingPoint.subCriterion.mainCriterion', $belongs))
                ->find($id),
            CriteriaNodeType::Version => null,
        };
    }
}
