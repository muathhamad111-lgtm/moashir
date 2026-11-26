<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Criteria\ScaleKind;
use App\Enums\Settings\SettingKey;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Services\Settings\SettingsStore;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function scoringTypesMigration(): object
{
    return require database_path('migrations/2026_11_19_220000_add_scoring_types_to_criteria.php');
}

/**
 * @return array<string, string|null>
 */
function storedTypes(string $code): array
{
    $point = DB::table('guiding_points')->where('code', $code)->first(['scoring_type', 'sub_criterion_id']);
    $sub = DB::table('sub_criteria')->where('id', $point?->sub_criterion_id)->first(['scoring_type', 'default_point_scoring_type', 'main_criterion_id']);
    $main = DB::table('main_criteria')->where('id', $sub?->main_criterion_id)->first(['default_scoring_type']);

    return [
        'main' => $main?->default_scoring_type,
        'sub' => $sub?->scoring_type,
        'sub_default' => $sub?->default_point_scoring_type,
        'point' => $point?->scoring_type,
    ];
}

/**
 * Builds a version whose single indicator carries the given code, so each version can be told apart.
 */
function versionWithPoint(string $prefix): void
{
    $version = draftVersion();
    $main = mainIn($version, $prefix, '1000.00');
    $sub = subIn($main, $prefix.'.1', '1000.00');
    pointIn($sub, $prefix.'.1.1', '1000.00');
}

it('gives every existing node the scale of its version and drafts the platform scale', function (): void {
    versionWithPoint('A');
    publishVersion(CriteriaVersion::query()->latest('id')->firstOrFail(), ScaleKind::Relative);
    DB::table('criteria_versions')->update(['status' => 'archived', 'archived_at' => now()]);
    versionWithPoint('B');
    publishVersion(CriteriaVersion::query()->latest('id')->firstOrFail(), ScaleKind::OutOfTen);
    versionWithPoint('C');
    app(SettingsStore::class)->put(SettingKey::AssessmentScaleKind, 'relative', AuditEvent::AssessmentSettingsUpdated, null);

    $migration = scoringTypesMigration();
    $migration->down();
    $migration->up();

    expect(storedTypes('A.1.1'))->toBe(['main' => 'relative', 'sub' => 'relative', 'sub_default' => 'relative', 'point' => 'relative'])
        ->and(storedTypes('B.1.1'))->toBe(['main' => 'score', 'sub' => 'score', 'sub_default' => 'score', 'point' => 'score'])
        ->and(storedTypes('C.1.1'))->toBe(['main' => 'relative', 'sub' => 'relative', 'sub_default' => 'relative', 'point' => 'relative']);
});

it('switches the structure guards back on, so a frozen version still refuses changes', function (): void {
    versionWithPoint('B');
    publishVersion(CriteriaVersion::query()->latest('id')->firstOrFail());

    $migration = scoringTypesMigration();
    $migration->down();
    $migration->up();

    expect(fn () => GuidingPoint::query()->where('code', 'B.1.1')->firstOrFail()->forceFill(['scoring_type' => 'achievement'])->save())
        ->toThrow(QueryException::class, 'criteria_structure');
});

it('refuses a type outside the three known ones', function (): void {
    versionWithPoint('C');

    expect(fn () => DB::table('guiding_points')->where('code', 'C.1.1')->update(['scoring_type' => 'out_of_5']))
        ->toThrow(QueryException::class, 'guiding_points_scoring_type_check');
});
