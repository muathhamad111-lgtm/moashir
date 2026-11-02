<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\DevelopmentSector;
use App\Enums\Entities\EntityStatus;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Support\ValueObjects\SearchTerm;

final readonly class EntityListFilters
{
    public function __construct(
        public ?SearchTerm $search = null,
        public ?EntityStatus $status = null,
        public ?int $regionId = null,
        public ?int $cityId = null,
        public ?EntityType $entityType = null,
        public ?DevelopmentSector $developmentSector = null,
        public ?OrgSize $orgSize = null,
        public bool $onlyDeleted = false,
        public string $sort = '-created_at',
        public int $perPage = 15,
    ) {}

    public function withStatus(?EntityStatus $status): self
    {
        return new self(
            $this->search, $status, $this->regionId, $this->cityId, $this->entityType,
            $this->developmentSector, $this->orgSize, $this->onlyDeleted, $this->sort, $this->perPage,
        );
    }
}
