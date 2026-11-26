<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Entities\DevelopmentSector;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Enums\Finance\PaymentPurpose;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

final readonly class BoardFilter
{
    public function __construct(
        public ?string $region = null,
        public ?AssessmentPath $path = null,
        public ?EntityType $type = null,
        public ?DevelopmentSector $sector = null,
        public ?OrgSize $size = null,
    ) {}

    public function regionOnly(): self
    {
        return new self(region: $this->region);
    }

    public function paymentPurpose(): ?PaymentPurpose
    {
        return $this->path instanceof AssessmentPath ? PaymentPurpose::forPath($this->path) : null;
    }

    public function hasEntityDimensions(): bool
    {
        return $this->region !== null || $this->type instanceof EntityType || $this->sector instanceof DevelopmentSector || $this->size instanceof OrgSize;
    }

    /**
     * @param  EloquentBuilder<covariant Model>|QueryBuilder  $query
     */
    public function applyToEntities(EloquentBuilder|QueryBuilder $query, string $table = 'entities'): void
    {
        if ($this->region !== null) {
            $query->whereIn($table.'.region_id', fn (QueryBuilder $regions): QueryBuilder => $regions->select('id')->from('regions')->where('code', $this->region));
        }

        if ($this->type instanceof EntityType) {
            $query->where($table.'.entity_type', $this->type->value);
        }

        if ($this->sector instanceof DevelopmentSector) {
            $query->where($table.'.development_sector', $this->sector->value);
        }

        if ($this->size instanceof OrgSize) {
            $query->where($table.'.org_size', $this->size->value);
        }
    }

    /**
     * @param  EloquentBuilder<covariant Model>|QueryBuilder  $query
     */
    public function applyToEntityOwned(EloquentBuilder|QueryBuilder $query, string $entityColumn): void
    {
        if (! $this->hasEntityDimensions()) {
            return;
        }

        $query->whereIn($entityColumn, function (QueryBuilder $entities): void {
            $entities->select('entities.id')->from('entities');
            $this->applyToEntities($entities);
        });
    }

    /**
     * @param  EloquentBuilder<covariant Model>|QueryBuilder  $query
     */
    public function applyToAssessments(EloquentBuilder|QueryBuilder $query, string $table = 'assessments'): void
    {
        $this->applyToEntityOwned($query, $table.'.entity_id');

        if ($this->path instanceof AssessmentPath) {
            $query->where($table.'.path', $this->path->value);
        }
    }

    /**
     * @param  EloquentBuilder<covariant Model>|QueryBuilder  $query
     */
    public function applyToPayments(EloquentBuilder|QueryBuilder $query, string $table = 'payments'): void
    {
        $this->applyToEntityOwned($query, $table.'.entity_id');

        $purpose = $this->paymentPurpose();

        if ($purpose instanceof PaymentPurpose) {
            $query->where($table.'.purpose', $purpose->value);
        }
    }
}
