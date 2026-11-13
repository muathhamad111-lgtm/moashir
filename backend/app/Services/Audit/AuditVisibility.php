<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\Audit\Audit;
use App\Models\Identity\User;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final class AuditVisibility
{
    /**
     * @var list<int>|null
     */
    private ?array $superAdminIds = null;

    /**
     * @param  Builder<Audit>  $query
     */
    public function restrictTo(Builder $query, User $viewer): void
    {
        if ($viewer->isSuperAdmin()) {
            return;
        }

        $hiddenIds = $this->superAdminIds();

        $query->whereNot(static fn (Builder $hidden): Builder => $hidden
            ->where('auditable_type', new User()->getMorphClass())
            ->whereIn('auditable_id', $hiddenIds === [] ? [0] : $hiddenIds));
    }

    public function isHiddenFrom(User $viewer, Audit $audit): bool
    {
        return ! $viewer->isSuperAdmin()
            && $audit->auditable_type === new User()->getMorphClass()
            && in_array($audit->auditable_id, $this->superAdminIds(), true);
    }

    public function masksActorFor(User $viewer, Audit $audit): bool
    {
        return ! $viewer->isSuperAdmin() && $audit->user_id !== null && in_array($audit->user_id, $this->superAdminIds(), true);
    }

    /**
     * @return list<int>
     */
    private function superAdminIds(): array
    {
        return $this->superAdminIds ??= array_values(array_map(ScalarValue::int(...), User::withTrashed()->superAdmins()->pluck('id')->all()));
    }
}
