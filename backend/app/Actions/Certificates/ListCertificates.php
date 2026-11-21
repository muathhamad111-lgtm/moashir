<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Data\Certificates\CertificateListFilters;
use App\Enums\Certificates\CertificateLevel;
use App\Enums\Certificates\CertificateStatus;
use App\Enums\Identity\PermissionKey;
use App\Models\Certificates\Certificate;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentRecordScope;
use App\Support\StatusCounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListCertificates
{
    public const array RELATIONS = ['assessment', 'replaces', 'issuedBy', 'revokedBy'];

    public function __construct(private AssessmentRecordScope $scope) {}

    /**
     * @return LengthAwarePaginator<int, Certificate>
     */
    public function handle(User $viewer, CertificateListFilters $filters): LengthAwarePaginator
    {
        return $this->filtered($viewer, $filters)
            ->with(self::RELATIONS)
            ->when($filters->statuses !== [], static fn (Builder $query): Builder => $query->whereIn('status', $filters->statuses))
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate($filters->perPage);
    }

    /**
     * @return array<string, int>
     */
    public function statusCountsOf(User $viewer, CertificateListFilters $filters): array
    {
        return StatusCounts::of($this->filtered($viewer, $filters), CertificateStatus::cases());
    }

    /**
     * @return Builder<Certificate>
     */
    private function filtered(User $viewer, CertificateListFilters $filters): Builder
    {
        return $this->visibleTo($viewer)
            ->when($filters->level instanceof CertificateLevel, static fn (Builder $query): Builder => $query->where('level', $filters->level))
            ->when($filters->entityId !== null, static fn (Builder $query): Builder => $query->where('entity_id', $filters->entityId))
            ->when($filters->assessmentId !== null, static fn (Builder $query): Builder => $query->where('assessment_id', $filters->assessmentId))
            ->when($filters->number !== null, static fn (Builder $query): Builder => $query->where('number', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters->number).'%'));
    }

    /**
     * @return Builder<Certificate>
     */
    private function visibleTo(User $viewer): Builder
    {
        $query = Certificate::query();

        if ($viewer->entity_id !== null) {
            return $query->ownedBy($viewer->entity_id);
        }

        if (! $viewer->can(PermissionKey::CertificatesManage->value)) {
            return $query->whereRaw('false');
        }

        return $query->whereHas('assessment', fn (Builder $assessment) => $this->scope->restrict($assessment, $viewer));
    }
}
