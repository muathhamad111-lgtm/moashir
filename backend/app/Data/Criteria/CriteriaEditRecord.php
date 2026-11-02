<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Enums\Criteria\CriteriaEditKind;
use App\Enums\Criteria\CriteriaEditScope;
use App\Models\Criteria\CriteriaVersion;

final readonly class CriteriaEditRecord
{
    /**
     * @param  list<CriteriaChange>  $changes
     */
    public function __construct(
        public CriteriaEditKind $kind,
        public CriteriaEditScope $scope,
        public CriteriaVersion $from,
        public ?CriteriaVersion $to,
        public array $changes,
        public ?string $reason,
    ) {}

    public function effect(): CriteriaEditEffect
    {
        return new CriteriaEditEffect($this->kind, $this->scope);
    }
}
