<?php

declare(strict_types=1);

use App\Data\Award\CycleAttributes;
use App\Data\Award\CycleDescriptionChange;
use App\Support\ValueObjects\LocalizedText;

it('leaves the description untouched unless the change carries it', function (): void {
    $untouched = new CycleAttributes(year: 2027);
    $cleared = new CycleAttributes(description: new CycleDescriptionChange(null));
    $text = new LocalizedText('تعريف الدورة', 'Cycle description');
    $set = new CycleAttributes(description: new CycleDescriptionChange($text));

    expect($untouched->touchedFields())->toBe(['year'])
        ->and($cleared->touchedFields())->toBe(['description'])
        ->and($cleared->columnValues())->toBe(['description' => null])
        ->and($cleared->isEmpty())->toBeFalse()
        ->and($set->columnValues())->toBe(['description' => $text]);
});

it('never carries a criteria version, which the server binds', function (): void {
    expect(new CycleAttributes(year: 2027, regionIds: [1])->touchedFields())->toBe(['year', 'region_ids']);
});
