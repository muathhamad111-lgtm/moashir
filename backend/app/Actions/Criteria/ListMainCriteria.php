<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Data\Criteria\MainCriteriaListQuery;
use App\Data\Criteria\MainCriteriaPage;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\MainCriterion;
use App\Support\Search\SearchTextNormalizer;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListMainCriteria
{
    public const array SORTABLE = ['order', '-order', 'name', '-name', 'weight', '-weight'];

    private const string NORMALIZED_NAME = SearchTextNormalizer::DATABASE_FUNCTION."(name->>'%s') like ?";

    public function handle(MainCriteriaListQuery $query, string $locale): MainCriteriaPage
    {
        $version = $this->versionFor($query->versionId);

        if (! $version instanceof CriteriaVersion) {
            return new MainCriteriaPage(null, new LengthAwarePaginator([], 0, $query->perPage));
        }

        $mains = $version->mainCriteria()->getQuery()
            ->reorder()
            ->with('subCriteria')
            ->when($query->search, fn (Builder $builder, SearchTerm $term): Builder => $this->matching($builder, $term))
            ->when($query->active !== null, static fn (Builder $builder): Builder => $builder->where('is_active', $query->active))
            ->orderBy($this->sortColumn($query->sort, $locale), str_starts_with($query->sort, '-') ? 'desc' : 'asc')
            ->orderBy('sort_order')
            ->paginate($query->perPage);

        return new MainCriteriaPage($version, $mains);
    }

    private function versionFor(?int $versionId): ?CriteriaVersion
    {
        if ($versionId !== null) {
            return CriteriaVersion::query()->findOrFail($versionId);
        }

        return CriteriaVersion::query()->draft()->first() ?? CriteriaVersion::query()->published()->first();
    }

    /**
     * @param  Builder<MainCriterion>  $builder
     * @return Builder<MainCriterion>
     */
    private function matching(Builder $builder, SearchTerm $term): Builder
    {
        $pattern = $term->containsPattern();

        return $builder->where(static fn (Builder $inner): Builder => $inner
            ->whereRaw(sprintf(self::NORMALIZED_NAME, 'ar'), [$pattern])
            ->orWhereRaw(sprintf(self::NORMALIZED_NAME, 'en'), [$pattern]));
    }

    private function sortColumn(string $sort, string $locale): string
    {
        return match (ltrim($sort, '-')) {
            'name' => $locale === 'en' ? 'name->en' : 'name->ar',
            'weight' => 'weight',
            default => 'sort_order',
        };
    }
}
