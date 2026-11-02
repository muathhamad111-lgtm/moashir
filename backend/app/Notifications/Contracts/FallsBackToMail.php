<?php

declare(strict_types=1);

namespace App\Notifications\Contracts;

use Illuminate\Notifications\Notification;

interface FallsBackToMail
{
    public function mailFallback(): Notification;
}
