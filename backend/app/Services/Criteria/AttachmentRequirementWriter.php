<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Data\Criteria\AttachmentRequirementData;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\SubCriterion;
use Illuminate\Support\Str;

final class AttachmentRequirementWriter
{
    /**
     * @param  list<AttachmentRequirementData>  $requirements
     */
    public function replace(SubCriterion|GuidingPoint $owner, array $requirements): void
    {
        $owner->attachmentRequirements()->delete();

        foreach ($requirements as $index => $requirement) {
            $owner->attachmentRequirements()->create([
                'key' => $requirement->key ?? Str::lower((string) Str::ulid()),
                'name' => $requirement->name,
                'is_required' => $requirement->isRequired,
                'sort_order' => $index + 1,
            ]);
        }

        $owner->unsetRelation('attachmentRequirements');
    }
}
