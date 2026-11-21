<?php

declare(strict_types=1);

namespace App\Actions\Insights;

use App\Data\Insights\ReportLibraryRow;
use App\Data\Insights\ResultCandidate;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Insights\LibraryFeedbackPdfLink;
use App\Services\Insights\VisibleAccreditedResults;
use App\Support\Insights\ResultEligibility;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListReportLibrary
{
    public function __construct(
        private VisibleAccreditedResults $results,
        private ResultEligibility $eligibility,
        private LibraryFeedbackPdfLink $feedbackPdfLinks,
        private Repository $config,
    ) {}

    /**
     * @return LengthAwarePaginator<int, ReportLibraryRow>
     */
    public function handle(User $viewer, Entity $entity, int $page): LengthAwarePaginator
    {
        $candidates = $this->results->of($viewer, $entity);
        usort($candidates, static fn (ResultCandidate $left, ResultCandidate $right): int => [$right->verifiedAt()?->getTimestamp() ?? PHP_INT_MAX, $right->id()]
            <=> [$left->verifiedAt()?->getTimestamp() ?? PHP_INT_MAX, $left->id()]);
        $perPage = $this->config->integer('moashir.insights.results_per_page');
        $rows = array_map(fn (ResultCandidate $candidate): ReportLibraryRow => $this->rowOf($viewer, $candidate), array_slice($candidates, ($page - 1) * $perPage, $perPage));

        return new LengthAwarePaginator($rows, count($candidates), $perPage, $page);
    }

    private function rowOf(User $viewer, ResultCandidate $candidate): ReportLibraryRow
    {
        return new ReportLibraryRow($candidate, $this->eligibility->reasonFor($candidate), $this->feedbackPdfLinks->for($viewer, $candidate->assessment));
    }
}
