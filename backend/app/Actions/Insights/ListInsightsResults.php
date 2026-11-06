<?php

declare(strict_types=1);

namespace App\Actions\Insights;

use App\Data\Insights\ResultCandidate;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Insights\VisibleAccreditedResults;
use App\Support\Insights\EligibleResultSelector;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListInsightsResults
{
    public function __construct(
        private VisibleAccreditedResults $results,
        private EligibleResultSelector $selector,
        private Repository $config,
    ) {}

    /**
     * @return LengthAwarePaginator<int, ResultCandidate>
     */
    public function handle(User $viewer, Entity $entity, int $page): LengthAwarePaginator
    {
        $eligible = $this->selector->eligible($this->results->of($viewer, $entity));
        $perPage = $this->config->integer('moashir.insights.results_per_page');

        return new LengthAwarePaginator(array_slice($eligible, ($page - 1) * $perPage, $perPage), count($eligible), $perPage, $page);
    }
}
