<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Entities\EntityDeletionImpact;
use App\Enums\Entities\ErasureRetainedRecord;
use App\Models\Entities\Entity;
use App\Services\Entities\OpenRequestDirectory;

final readonly class GetEntityDeletionImpact
{
    public function __construct(private OpenRequestDirectory $openRequests) {}

    public function handle(Entity $entity): EntityDeletionImpact
    {
        return new EntityDeletionImpact($this->openRequests->openRequestsOf($entity), ErasureRetainedRecord::cases());
    }
}
