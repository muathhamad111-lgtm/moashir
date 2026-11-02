<?php

declare(strict_types=1);

namespace App\Policies\Search;

use App\Models\Identity\User;
use App\Services\Search\SearchProviderRegistry;

final readonly class GlobalSearchPolicy
{
    public function __construct(private SearchProviderRegistry $registry) {}

    public function search(User $actor): bool
    {
        return $this->registry->hasAnyAvailableTo($actor);
    }
}
