<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Models\Identity\User;
use App\Support\ScalarValue;

final class IntegrationActorNames
{
    /**
     * @param  list<int>  $userIds
     * @return array<int, string>
     */
    public function for(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter($userIds, static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        $names = [];

        foreach (User::query()->whereKey($ids)->get(['id', 'name']) as $user) {
            $names[$user->id] = ScalarValue::string($user->name);
        }

        return $names;
    }
}
