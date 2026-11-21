<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Contracts\Dashboards\DashboardBoardQuery;
use App\Enums\Dashboards\DashboardBoard;
use Illuminate\Container\Attributes\Tag;
use LogicException;

final readonly class DashboardBoardQueries
{
    public const string TAG = 'dashboards.boards';

    /**
     * @var array<string, DashboardBoardQuery>
     */
    private array $queries;

    /**
     * @param  iterable<DashboardBoardQuery>  $queries
     */
    public function __construct(#[Tag(self::TAG)] iterable $queries)
    {
        $byBoard = [];

        foreach ($queries as $query) {
            $byBoard[$query->board()->value] = $query;
        }

        $this->queries = $byBoard;
    }

    public function for(DashboardBoard $board): DashboardBoardQuery
    {
        return $this->queries[$board->value] ?? throw new LogicException("No query is registered for the {$board->value} board.");
    }
}
