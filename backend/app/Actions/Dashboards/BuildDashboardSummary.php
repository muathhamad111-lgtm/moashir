<?php

declare(strict_types=1);

namespace App\Actions\Dashboards;

use App\Data\Dashboards\DashboardSummary;
use App\Enums\Dashboards\DashboardBoard;
use App\Exceptions\Dashboards\DashboardRejected;
use App\Models\Identity\User;
use App\Services\Dashboards\BoardHoldings;
use App\Services\Dashboards\DashboardBoardQueries;
use Illuminate\Support\Facades\Date;

final readonly class BuildDashboardSummary
{
    public function __construct(
        private BoardHoldings $holdings,
        private DashboardBoardQueries $queries,
        private BuildActionCenter $actionCenter,
    ) {}

    public function handle(User $user, ?DashboardBoard $requested): DashboardSummary
    {
        $held = $this->holdings->of($user);
        $board = $requested ?? $held->landing;

        if (! $held->holds($board)) {
            throw DashboardRejected::boardNotHeld();
        }

        return new DashboardSummary(
            $board,
            $held,
            $this->queries->for($board)->build($user),
            $this->actionCenter->handle($user),
            Date::now(),
        );
    }
}
