<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Boards;

use App\Contracts\Dashboards\DashboardBoardQuery;
use App\Data\Dashboards\BoardContent;
use App\Data\Dashboards\DashboardCard;
use App\Data\Dashboards\DashboardWidget;
use App\Enums\Dashboards\DashboardBoard;
use App\Models\Cms\CmsPage;
use App\Models\Identity\User;
use App\Models\Kpis\Kpi;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\DashboardLinks as Links;

final readonly class ContentOfficerBoardQuery implements DashboardBoardQuery
{
    private const int LATEST_EDITS = 5;

    public function board(): DashboardBoard
    {
        return DashboardBoard::ContentOfficer;
    }

    public function build(User $user): BoardContent
    {
        $pages = AggregateRow::counts(CmsPage::query(), [
            'total' => ['true', []],
            'published' => ['is_published', []],
            'draft_changes' => ['has_draft_changes', []],
        ]);
        $kpis = AggregateRow::counts(Kpi::query(), [
            'total' => ['true', []],
            'published' => ['is_published', []],
            'featured' => ['is_published and show_on_homepage', []],
        ]);

        return new BoardContent([
            new DashboardCard('pages_total', $pages['total'] ?? 0, link: Links::HOMEPAGE),
            new DashboardCard('pages_published', $pages['published'] ?? 0, link: Links::HOMEPAGE),
            new DashboardCard('pages_with_draft_changes', $pages['draft_changes'] ?? 0, link: Links::HOMEPAGE),
            new DashboardCard('kpis_total', $kpis['total'] ?? 0, link: Links::KPIS),
            new DashboardCard('kpis_published', $kpis['published'] ?? 0, link: Links::KPIS),
            new DashboardCard('kpis_featured', $kpis['featured'] ?? 0, link: Links::KPIS),
        ], [
            new DashboardWidget('latest_edits', $this->latestEdits()),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function latestEdits(): array
    {
        $pages = CmsPage::query()->whereNotNull('content_updated_at')->latest('content_updated_at')->limit(self::LATEST_EDITS)
            ->get(['id', 'key', 'title', 'content_updated_at'])
            ->map(static fn (CmsPage $page): array => [
                'type' => 'page',
                'id' => $page->id,
                'key' => $page->key->value,
                'title' => $page->title?->toArray(),
                'updated_at' => $page->content_updated_at?->toIso8601String() ?? '',
            ]);
        $kpis = Kpi::query()->latest('updated_at')->limit(self::LATEST_EDITS)
            ->get(['id', 'slug', 'name', 'updated_at'])
            ->map(static fn (Kpi $kpi): array => [
                'type' => 'kpi',
                'id' => $kpi->id,
                'key' => $kpi->slug,
                'title' => $kpi->name->toArray(),
                'updated_at' => $kpi->updated_at->toIso8601String(),
            ]);

        return array_values($pages->concat($kpis)->sortByDesc('updated_at')->take(self::LATEST_EDITS)->values()->all());
    }
}
