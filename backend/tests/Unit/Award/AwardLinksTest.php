<?php

declare(strict_types=1);

use App\Support\Award\AwardLinks;

it('links the staff cycle notices to the cycle page, not the setup wizard', function (): void {
    expect(AwardLinks::cycle(31))->toBe('/admin/award/cycles/31')
        ->and(AwardLinks::committee(31))->toBe('/admin/award/cycles/31/committee');
});
