<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Data\Criteria\CriteriaVersionSummary;
use App\Enums\Criteria\CriteriaVersionStatus;
use App\Models\Criteria\CriteriaVersion;
use App\Services\Criteria\CriteriaVersionSummarizer;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListCriteriaVersions
{
    public function __construct(private CriteriaVersionSummarizer $summarizer) {}

    /**
     * @return LengthAwarePaginator<int, CriteriaVersionSummary>
     */
    public function handle(?CriteriaVersionStatus $status, int $perPage): LengthAwarePaginator
    {
        $page = CriteriaVersion::query()
            ->when($status instanceof CriteriaVersionStatus, static fn ($query) => $query->where('status', $status))
            ->orderByDesc('version_number')
            ->paginate($perPage);

        return new LengthAwarePaginator(
            $this->summarizer->summarize($page->getCollection()),
            $page->total(),
            $page->perPage(),
            $page->currentPage(),
        );
    }
}
