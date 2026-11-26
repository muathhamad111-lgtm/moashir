<?php

declare(strict_types=1);

namespace App\Services\Nda;

use App\Data\Nda\NdaRegisterSummary;
use App\Enums\Identity\TermsKind;
use App\Enums\Nda\NdaAcceptanceFilter;
use App\Models\Identity\TermsAcceptance;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

/**
 * The in-scope staff against one agreement version, filtered by whether they accepted it.
 */
final readonly class NdaRegisterQuery
{
    public function __construct(private NdaAudience $audience) {}

    /**
     * @return Builder<User>
     */
    public function users(TermsVersion $version, NdaAcceptanceFilter $filter): Builder
    {
        $accepted = static fn (Builder $acceptance): Builder => $acceptance->where('terms_version_id', $version->id);
        $query = $this->audience->inScope();

        return match ($filter) {
            NdaAcceptanceFilter::All => $query,
            NdaAcceptanceFilter::Pending => $query->whereDoesntHave('termsAcceptances', $accepted),
            NdaAcceptanceFilter::Accepted => $query->whereHas('termsAcceptances', $accepted),
        };
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, TermsAcceptance>
     */
    public function acceptancesOf(TermsVersion $version, array $userIds): array
    {
        $byUser = [];

        foreach (TermsAcceptance::query()->where('terms_version_id', $version->id)->whereIn('user_id', $userIds)->get() as $acceptance) {
            $byUser[$acceptance->user_id] = $acceptance;
        }

        return $byUser;
    }

    /**
     * The latest agreement version each user accepted, for those who have not accepted the current one yet.
     *
     * @param  list<int>  $userIds
     * @return array<int, string> keyed by user id
     */
    public function lastAcceptedVersionsOf(array $userIds): array
    {
        $versions = [];
        $rows = TermsAcceptance::query()
            ->join('terms_versions', 'terms_versions.id', '=', 'terms_acceptances.terms_version_id')
            ->where('terms_versions.kind', TermsKind::Nda->value)
            ->whereIn('terms_acceptances.user_id', $userIds)
            ->orderBy('terms_acceptances.created_at')
            ->get(['terms_acceptances.user_id', 'terms_versions.version']);

        foreach ($rows as $row) {
            $versions[$row->user_id] = ScalarValue::string($row->getAttribute('version'));
        }

        return $versions;
    }

    public function summary(TermsVersion $version): NdaRegisterSummary
    {
        return new NdaRegisterSummary(
            $this->audience->inScope()->count(),
            $this->users($version, NdaAcceptanceFilter::Accepted)->count(),
        );
    }
}
