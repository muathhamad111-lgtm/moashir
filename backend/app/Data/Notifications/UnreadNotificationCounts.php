<?php

declare(strict_types=1);

namespace App\Data\Notifications;

final readonly class UnreadNotificationCounts
{
    /**
     * @param  array<string, int>  $byCategory
     */
    public function __construct(public int $total, public array $byCategory) {}
}
