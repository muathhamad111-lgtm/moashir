<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Identity\AssessorQualification;
use App\Models\Identity\User;
use App\Models\Identity\UserAssessorQualification;
use App\Models\Identity\UserRequestPath;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Which assessments a staff account may take: its assessor qualifications and the request paths it
 * follows (none granted = every path).
 *
 * @mixin User
 */
trait HasAssessmentScope
{
    /**
     * @return HasMany<UserAssessorQualification, $this>
     */
    public function assessorQualifications(): HasMany
    {
        return $this->hasMany(UserAssessorQualification::class);
    }

    /**
     * @return HasMany<UserRequestPath, $this>
     */
    public function requestPaths(): HasMany
    {
        return $this->hasMany(UserRequestPath::class);
    }

    /**
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function qualifiedFor(Builder $query, AssessorQualification $qualification): void
    {
        $query->whereHas('assessorQualifications', static fn (Builder $held): Builder => $held->where('qualification', $qualification));
    }

    /**
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function followingPath(Builder $query, AssessmentPath $path): void
    {
        $query->where(static fn (Builder $scoped): Builder => $scoped
            ->whereDoesntHave('requestPaths')
            ->orWhereHas('requestPaths', static fn (Builder $granted): Builder => $granted->where('path', $path)));
    }

    /**
     * @return list<AssessorQualification>
     */
    public function qualifications(): array
    {
        $held = $this->loadMissing('assessorQualifications')->assessorQualifications
            ->map(static fn (UserAssessorQualification $granted): AssessorQualification => $granted->qualification)
            ->all();

        return array_values(array_filter(AssessorQualification::cases(), static fn (AssessorQualification $qualification): bool => in_array($qualification, $held, true)));
    }

    public function isQualifiedFor(AssessorQualification $qualification): bool
    {
        return in_array($qualification, $this->qualifications(), true);
    }

    /**
     * @return list<AssessmentPath>
     */
    public function grantedRequestPaths(): array
    {
        return array_values($this->loadMissing('requestPaths')->requestPaths
            ->map(static fn (UserRequestPath $granted): AssessmentPath => $granted->path)
            ->all());
    }
}
