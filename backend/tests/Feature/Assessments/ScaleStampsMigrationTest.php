<?php

declare(strict_types=1);

use App\Models\Assessments\Assessment;
use Illuminate\Support\Facades\DB;

function scaleStampsMigration(): object
{
    return require database_path('migrations/2026_11_20_100000_make_assessment_scale_stamps_optional.php');
}

it('stamps unstamped requests with their version scale on rollback and allows no stamp again after', function (): void {
    publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    expect($draft->scale_kind)->toBeNull();

    $migration = scaleStampsMigration();
    $migration->down();
    expect(Assessment::query()->findOrFail($draft->id)->scale_kind?->value)->toBe('of_10');

    $migration->up();
    DB::table('assessments')->where('id', $draft->id)->update(['scale_kind' => null]);
    expect(Assessment::query()->findOrFail($draft->id)->scale_kind)->toBeNull();
});
