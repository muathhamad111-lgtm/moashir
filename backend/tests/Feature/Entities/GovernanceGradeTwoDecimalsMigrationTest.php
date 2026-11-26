<?php

declare(strict_types=1);

use App\Models\Entities\Entity;
use Illuminate\Support\Facades\DB;

function governanceGradeTwoDecimals(): object
{
    return require database_path('migrations/2026_11_16_100000_round_entity_governance_grade_to_two_decimals.php');
}

it('rounds stored three-decimal governance grades half up to two decimals and keeps empty grades empty', function (): void {
    $migration = governanceGradeTwoDecimals();
    $rounded = Entity::factory()->create();
    $empty = Entity::factory()->create(['governance_grade' => null]);

    $migration->down();
    DB::table('entities')->where('id', $rounded->id)->update(['governance_grade' => '87.455']);
    $migration->up();

    expect(DB::table('entities')->where('id', $rounded->id)->value('governance_grade'))->toBe('87.46')
        ->and(DB::table('entities')->where('id', $empty->id)->value('governance_grade'))->toBeNull();
});
