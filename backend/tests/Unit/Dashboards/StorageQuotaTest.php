<?php

declare(strict_types=1);

use App\Support\ValueObjects\StorageQuota;

it('defaults to 50 GiB with warnings at 80 and 95 percent', function (): void {
    expect(config('moashir.storage'))->toBe(['quota_gb' => 50, 'warning_percent' => 80, 'critical_percent' => 95]);
});

it('converts the quota to bytes', function (): void {
    $quota = new StorageQuota(50, 80, 95);

    expect($quota->bytes())->toBe(53_687_091_200)
        ->and($quota->warningPercent)->toBe(80)
        ->and($quota->criticalPercent)->toBe(95);
});

it('refuses a quota or thresholds that cannot hold', function (int $gigabytes, int $warning, int $critical): void {
    new StorageQuota($gigabytes, $warning, $critical);
})->with([
    'no space' => [0, 80, 95],
    'no warning' => [50, 0, 95],
    'warning above critical' => [50, 96, 95],
    'equal thresholds' => [50, 90, 90],
    'critical above the whole' => [50, 80, 101],
])->throws(InvalidArgumentException::class);
