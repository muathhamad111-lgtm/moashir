<?php

declare(strict_types=1);

namespace App\Contracts\Identity;

use App\Data\Identity\CommitteeAppointment;
use App\Models\Identity\User;

interface CommitteeAppointmentsReader
{
    /**
     * @return list<CommitteeAppointment>
     */
    public function of(User $user): array;
}
