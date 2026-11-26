<?php

declare(strict_types=1);

use App\Models\Award\AwardCycle;
use Illuminate\Support\Facades\Schema;

function cycleDescriptionMigration(): object
{
    return require database_path('migrations/2026_11_25_120000_add_description_to_award_cycles.php');
}

it('drops the cycle description on rollback and adds it back as an optional column', function (): void {
    $migration = cycleDescriptionMigration();

    $migration->down();

    expect(Schema::hasColumn('award_cycles', 'description'))->toBeFalse();

    $migration->up();

    $cycle = AwardCycle::factory()->create();

    expect(Schema::hasColumn('award_cycles', 'description'))->toBeTrue()
        ->and(Schema::getColumnType('award_cycles', 'description'))->toBe('jsonb')
        ->and($cycle->refresh()->description)->toBeNull();
});
