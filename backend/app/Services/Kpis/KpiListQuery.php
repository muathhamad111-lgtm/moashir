<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Data\Kpis\KpiListFilters;
use App\Enums\Kpis\KpiSort;
use App\Enums\Kpis\KpiStatus;
use App\Models\Kpis\Kpi;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;

final class KpiListQuery
{
    /**
     * @return Builder<Kpi>
     */
    public function filtered(KpiListFilters $filters, string $locale): Builder
    {
        $query = Kpi::query();

        $this->whereFlag($query, 'is_published', $filters->published);
        $this->whereFlag($query, 'show_on_homepage', $filters->featured);
        $this->whereValue($query, 'kpi_category_id', $filters->categoryId);
        $this->whereValue($query, 'kpi_type_id', $filters->typeId);
        $this->whereValue($query, 'measurement_year', $filters->measurementYear);

        if ($filters->status instanceof KpiStatus) {
            $query->where('status', $filters->status);
        }

        $this->whereLocalizedMatches($query, 'name', $filters->search);
        $this->whereLocalizedMatches($query, 'owner', $filters->owner);
        $this->sort($query, $filters->sort, $locale);

        return $query;
    }

    /**
     * @param  Builder<Kpi>  $query
     */
    private function whereFlag(Builder $query, string $column, ?bool $value): void
    {
        if ($value !== null) {
            $query->where($column, $value);
        }
    }

    /**
     * @param  Builder<Kpi>  $query
     */
    private function whereValue(Builder $query, string $column, ?int $value): void
    {
        if ($value !== null) {
            $query->where($column, $value);
        }
    }

    /**
     * @param  Builder<Kpi>  $query
     */
    private function whereLocalizedMatches(Builder $query, string $column, ?SearchTerm $term): void
    {
        if (! $term instanceof SearchTerm) {
            return;
        }

        $pattern = $term->containsPattern();
        [$arabic, $english] = $column === 'owner'
            ? ["moashir_search_normalize(owner->>'ar') like ?", "moashir_search_normalize(owner->>'en') like ?"]
            : ["moashir_search_normalize(name->>'ar') like ?", "moashir_search_normalize(name->>'en') like ?"];
        $query->where(static fn (Builder $text): Builder => $text
            ->whereRaw($arabic, [$pattern])
            ->orWhereRaw($english, [$pattern]));
    }

    /**
     * @param  Builder<Kpi>  $query
     */
    private function sort(Builder $query, KpiSort $sort, string $locale): void
    {
        $direction = $sort->isDescending() ? 'desc' : 'asc';
        $language = $locale === 'en' ? 'en' : 'ar';

        match ($sort) {
            KpiSort::NameAscending, KpiSort::NameDescending => $query->orderByRaw("coalesce(nullif(name->>'{$language}', ''), name->>'ar', name->>'en') {$direction}"),
            KpiSort::HighestValue, KpiSort::LowestValue => $query->orderByRaw("value {$direction} nulls last"),
            KpiSort::LatestUpdate, KpiSort::EarliestUpdate => $query->orderByRaw("value_updated_at {$direction} nulls last"),
            KpiSort::Newest, KpiSort::Oldest => $query->orderBy('created_at', $direction),
            KpiSort::RecentlyEdited => $query->orderBy('updated_at', $direction),
            KpiSort::Order => $query->orderBy('sort_order'),
        };

        $query->orderBy('id');
    }
}
