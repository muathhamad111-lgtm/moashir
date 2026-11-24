<?php

declare(strict_types=1);

use App\Support\Award\AwardLinks;

it('links the staff cycle notices straight to the cycle setup', function (): void {
    expect(AwardLinks::cycle(31))->toBe('/admin/award/cycles/31/setup')
        ->and(AwardLinks::committee(31))->toBe('/admin/award/cycles/31/committee');
});
