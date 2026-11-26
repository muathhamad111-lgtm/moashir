<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Data\Audit\AuditLogFilters;
use App\Models\Audit\Audit;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final readonly class AuditLogQuery
{
    private const string NAMESPACE_SEPARATOR = '\\';

    public function __construct(private AuditVisibility $visibility) {}

    /**
     * @return Builder<Audit>
     */
    public function for(User $viewer, AuditLogFilters $filters): Builder
    {
        $query = Audit::query();
        $this->visibility->restrictTo($query, $viewer);

        return $query
            ->when($filters->from instanceof CarbonImmutable, static fn (Builder $scoped): Builder => $scoped->where('created_at', '>=', $filters->from))
            ->when($filters->to instanceof CarbonImmutable, static fn (Builder $scoped): Builder => $scoped->where('created_at', '<=', $filters->to))
            ->when($filters->actorId !== null, static fn (Builder $scoped): Builder => $scoped->where('user_id', $filters->actorId))
            ->when($filters->event !== null, static fn (Builder $scoped): Builder => $scoped->where('event', $filters->event))
            ->when($filters->subjectType !== null, static fn (Builder $scoped): Builder => $scoped->where('auditable_type', 'like', self::typePattern((string) $filters->subjectType)))
            ->when($filters->subjectId !== null, static fn (Builder $scoped): Builder => $scoped->where('auditable_id', $filters->subjectId))
            ->when($filters->ip !== null, static fn (Builder $scoped): Builder => $scoped->where('ip_address', $filters->ip));
    }

    public static function subjectTypeOf(string $auditableType): string
    {
        return Str::snake(class_basename($auditableType));
    }

    private static function typePattern(string $subjectType): string
    {
        return '%'.addcslashes(self::NAMESPACE_SEPARATOR.Str::studly($subjectType), '\\%_');
    }
}
