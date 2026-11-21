<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\UserListFilters;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\User;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Date;

final class ListStaffUsers
{
    public const array SORTABLE = ['name', '-name', 'last_login_at', '-last_login_at', 'created_at', '-created_at'];

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(UserListFilters $filters): LengthAwarePaginator
    {
        $direction = str_starts_with($filters->sort, '-') ? 'desc' : 'asc';

        return $this->filteredQuery($filters)
            ->with(User::ADMINISTRATION_RELATIONS)
            ->orderBy(ltrim($filters->sort, '-'), $direction)
            ->orderBy('id')
            ->paginate($filters->perPage);
    }

    /**
     * @return Builder<User>
     */
    public function filteredQuery(UserListFilters $filters): Builder
    {
        $query = User::query()->withoutSuperAdmins();

        $filters->role === RoleKey::Entity
            ? $query->whereNotNull('entity_id')
            : $query->staff();

        if ($filters->role instanceof RoleKey && $filters->role !== RoleKey::Entity) {
            $query->role($filters->role->value);
        }

        if ($filters->search instanceof SearchTerm) {
            $query->matchingSearch($filters->search);
        }

        if ($filters->status instanceof UserStatus) {
            $this->applyStatus($query, $filters->status);
        }

        return $query;
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyStatus(Builder $query, UserStatus $status): void
    {
        if ($status === UserStatus::Locked) {
            $query->where('locked_until', '>', Date::now());

            return;
        }

        $query->where('status', $status);

        if ($status === UserStatus::Active) {
            $query->where(static fn (Builder $inner): Builder => $inner->whereNull('locked_until')->orWhere('locked_until', '<=', Date::now()));
        }
    }
}
