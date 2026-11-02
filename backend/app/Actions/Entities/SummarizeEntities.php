<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Entities\EntityListFilters;
use App\Enums\Entities\EntityStatus;
use App\Models\Identity\User;

final readonly class SummarizeEntities
{
    public function __construct(private ListEntities $listEntities) {}

    /**
     * @return array<string, int>
     */
    public function handle(User $actor, EntityListFilters $filters): array
    {
        $stats = ['total' => $this->listEntities->filteredQuery($actor, $filters->withStatus(null))->count()];

        foreach (EntityStatus::cases() as $status) {
            $stats[$status->value] = $this->listEntities->filteredQuery($actor, $filters->withStatus($status))->count();
        }

        return $stats;
    }
}
