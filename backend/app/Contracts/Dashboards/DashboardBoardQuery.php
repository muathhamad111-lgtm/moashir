<?php

declare(strict_types=1);

namespace App\Contracts\Dashboards;

use App\Data\Dashboards\BoardContent;
use App\Enums\Dashboards\DashboardBoard;
use App\Models\Identity\User;

interface DashboardBoardQuery
{
    public function board(): DashboardBoard;

    public function build(User $user): BoardContent;
}
