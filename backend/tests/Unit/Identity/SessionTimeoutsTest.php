<?php

declare(strict_types=1);

use App\Services\Identity\SessionTimeouts;
use Illuminate\Support\Carbon;

it('applies the idle limit of the user group', function (Closure $user, int $minutes): void {
    $timeouts = app(SessionTimeouts::class);
    $now = Carbon::now();
    $subject = $user();

    expect($timeouts->idleMinutesFor($subject))->toBe($minutes)
        ->and($timeouts->hasExpired($subject, $now->getTimestamp(), $now->copy()->subMinutes($minutes)->getTimestamp(), $now))->toBeFalse()
        ->and($timeouts->hasExpired($subject, $now->getTimestamp(), $now->copy()->subMinutes($minutes + 1)->getTimestamp(), $now))->toBeTrue();
})->with([
    'super admin' => [superAdmin(...), 15],
    'staff' => [systemAdmin(...), 30],
    'entity' => [entityUser(...), 60],
]);

it('applies the absolute limit regardless of activity', function (): void {
    $timeouts = app(SessionTimeouts::class);
    $now = Carbon::now();

    expect($timeouts->hasExpired(entityUser(), $now->copy()->subHours(13)->getTimestamp(), $now->getTimestamp(), $now))->toBeTrue()
        ->and($timeouts->absoluteExpiry($now->getTimestamp())->getTimestamp())->toBe($now->copy()->addHours(12)->getTimestamp());
});
