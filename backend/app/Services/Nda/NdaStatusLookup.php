<?php

declare(strict_types=1);

namespace App\Services\Nda;

use App\Enums\Nda\NdaUserStatus;
use App\Models\Identity\TermsAcceptance;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Support\ScalarValue;

/**
 * The agreement status of a page of users in two queries, for the users list column.
 */
final readonly class NdaStatusLookup
{
    public function __construct(private NdaAcceptanceCheck $check, private NdaScope $scope) {}

    /**
     * @param  list<User>  $users
     * @return array<int, NdaUserStatus> keyed by user id
     */
    public function of(array $users): array
    {
        $current = $this->check->current();
        $inScope = $this->scope->roles();
        $accepted = $current instanceof TermsVersion ? $this->acceptedIds($current, $users) : [];
        $statuses = [];

        foreach ($users as $user) {
            $statuses[$user->id] = match (true) {
                ! $current instanceof TermsVersion || ! $this->scope->coversWithin($user, $inScope) => NdaUserStatus::OutOfScope,
                in_array($user->id, $accepted, true) => NdaUserStatus::Accepted,
                default => NdaUserStatus::Pending,
            };
        }

        return $statuses;
    }

    /**
     * What the users list needs to label the column and warn when an in-scope role is added.
     *
     * @return array{nda_scope_roles: list<string>, nda_current_version: string|null}
     */
    public function meta(): array
    {
        return ['nda_scope_roles' => $this->scope->roleValues(), 'nda_current_version' => $this->check->current()?->version];
    }

    /**
     * @param  list<User>  $users
     * @return list<int>
     */
    private function acceptedIds(TermsVersion $version, array $users): array
    {
        $ids = TermsAcceptance::query()
            ->where('terms_version_id', $version->id)
            ->whereIn('user_id', array_map(static fn (User $user): int => $user->id, $users))
            ->pluck('user_id')
            ->all();

        return array_values(array_map(static fn (mixed $id): int => ScalarValue::int($id), $ids));
    }
}
