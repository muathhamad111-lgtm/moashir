<?php

declare(strict_types=1);

namespace App\Notifications\Contracts;

use App\Data\Notifications\InAppMessage;
use App\Models\Identity\User;

interface DeliversInApp
{
    public function toInApp(User $notifiable): InAppMessage;
}
