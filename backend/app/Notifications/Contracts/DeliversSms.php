<?php

declare(strict_types=1);

namespace App\Notifications\Contracts;

use App\Enums\Integrations\SmsPurpose;
use App\Models\Identity\User;

interface DeliversSms
{
    public function toSms(User $notifiable): string;

    public function smsPurpose(): SmsPurpose;
}
