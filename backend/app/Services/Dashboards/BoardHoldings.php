<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Contracts\Identity\CommitteeAppointmentsReader;
use App\Data\Dashboards\HeldBoards;
use App\Data\Identity\CommitteeAppointment;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Dashboards\DashboardBoard;
use App\Enums\Identity\PermissionKey;
use App\Exceptions\Dashboards\DashboardRejected;
use App\Models\Identity\User;

final readonly class BoardHoldings
{
    public function __construct(private CommitteeAppointmentsReader $appointments) {}

    public function of(User $user): HeldBoards
    {
        $appointments = $this->appointments->of($user);
        $boards = $this->boardsOf($user, $appointments !== []);

        if ($boards === []) {
            throw DashboardRejected::boardNotHeld();
        }

        return new HeldBoards($boards, $this->inCommitteeWindow($appointments) ? DashboardBoard::CommitteeAssessor : $boards[0]);
    }

    /**
     * @return list<DashboardBoard>
     */
    private function boardsOf(User $user, bool $seated): array
    {
        $boards = $user->isSuperAdmin()
            ? [DashboardBoard::SystemAdmin]
            : array_map(DashboardBoard::ofRole(...), $user->loadMissing('roles')->roleKeys());

        if ($seated) {
            $boards[] = DashboardBoard::CommitteeAssessor;
        }

        if ($user->can(PermissionKey::DashboardExecutiveView->value)) {
            $boards[] = DashboardBoard::Executive;
        }

        $unique = [];

        foreach ($boards as $board) {
            $unique[$board->value] = $board;
        }

        usort($unique, static fn (DashboardBoard $first, DashboardBoard $second): int => $first->precedence() <=> $second->precedence());

        return $unique;
    }

    /**
     * @param  list<CommitteeAppointment>  $appointments
     */
    private function inCommitteeWindow(array $appointments): bool
    {
        return array_any($appointments, static fn (CommitteeAppointment $appointment): bool => AwardCycleStatus::tryFrom($appointment->cycleStatus)?->isCommitteeStage() === true);
    }
}
