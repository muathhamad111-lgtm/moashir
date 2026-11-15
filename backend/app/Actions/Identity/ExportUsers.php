<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Data\Identity\UserExport;
use App\Data\Identity\UserListFilters;
use App\Enums\Audit\AuditEvent;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Support\Facades\Date;

final readonly class ExportUsers
{
    private const int CHUNK = 500;

    public function __construct(private ListStaffUsers $listUsers, private AuditRecorder $audit) {}

    public function handle(User $actor, UserListFilters $filters, string $locale): UserExport
    {
        $query = $this->listUsers->filteredQuery($filters);
        $rowCount = (clone $query)->count();

        $this->audit->record(new AuditEntry(AuditEvent::UsersExported, $actor, [], [
            'row_count' => $rowCount,
            'filters' => [
                'search' => $filters->search instanceof SearchTerm,
                'role' => $filters->role?->value,
                'status' => $filters->status?->value,
            ],
        ], ['export']));

        return new UserExport(
            'users-'.Date::now()->format('Ymd-His').'.csv',
            $rowCount,
            $query->with('roles')->lazyById(self::CHUNK),
            $locale,
        );
    }
}
