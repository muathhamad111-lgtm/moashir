<?php

declare(strict_types=1);

namespace App\Actions\Nda;

use App\Data\Nda\NdaRegisterPage;
use App\Data\Nda\NdaRegisterSummary;
use App\Enums\Nda\NdaAcceptanceFilter;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Services\Nda\NdaAcceptanceCheck;
use App\Services\Nda\NdaRegisterQuery;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The acceptance register: every in-scope staff account against the current version, one page at a time.
 */
final readonly class ListNdaAcceptances
{
    public function __construct(private NdaAcceptanceCheck $check, private NdaRegisterQuery $register) {}

    public function handle(NdaAcceptanceFilter $filter, int $perPage): NdaRegisterPage
    {
        $version = $this->check->current();

        if (! $version instanceof TermsVersion) {
            return new NdaRegisterPage(null, new LengthAwarePaginator([], 0, $perPage), [], new NdaRegisterSummary(0, 0));
        }

        $users = $this->register->users($version, $filter)->with('roles')->orderBy('name')->orderBy('id')->paginate($perPage);
        $ids = array_values(array_map(static fn (User $user): int => $user->id, $users->items()));

        return new NdaRegisterPage(
            $version,
            $users,
            $this->register->acceptancesOf($version, $ids),
            $this->register->summary($version),
            $this->register->lastAcceptedVersionsOf($ids),
        );
    }
}
