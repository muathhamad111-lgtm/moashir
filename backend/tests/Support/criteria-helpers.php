<?php

declare(strict_types=1);

use App\Actions\Criteria\InstallCriteriaSeed;
use App\Console\Commands\InstallCriteriaSeedCommand;
use App\Enums\Criteria\CriteriaVersionStatus;
use App\Enums\Criteria\ScaleKind;
use App\Enums\Identity\RoleKey;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Support\ValueObjects\Weight;
use Illuminate\Support\Facades\Date;

function criteriaOfficer(): User
{
    return staffUser(RoleKey::CriteriaOfficer);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function draftVersion(array $attributes = []): CriteriaVersion
{
    return CriteriaVersion::factory()->create($attributes);
}

function publishVersion(CriteriaVersion $version, ScaleKind $scale = ScaleKind::OutOfTen): CriteriaVersion
{
    $version->forceFill(['status' => CriteriaVersionStatus::Published, 'published_at' => Date::now(), 'scale_kind' => $scale])->save();

    return $version->refresh();
}

function archiveVersion(CriteriaVersion $version): CriteriaVersion
{
    $version->forceFill(['status' => CriteriaVersionStatus::Archived, 'archived_at' => Date::now()])->save();

    return $version->refresh();
}

function mainIn(CriteriaVersion $version, string $code, string $weight, int $order = 1): MainCriterion
{
    return MainCriterion::factory()->for($version, 'criteriaVersion')->create([
        'code' => $code,
        'sort_order' => $order,
        'weight' => Weight::of($weight),
    ]);
}

function subIn(MainCriterion $main, string $code, string $weight, int $order = 1): SubCriterion
{
    return SubCriterion::factory()->for($main, 'mainCriterion')->create([
        'code' => $code,
        'sort_order' => $order,
        'weight' => Weight::of($weight),
    ]);
}

function directSubIn(MainCriterion $main, string $code, string $weight, int $order = 1): SubCriterion
{
    return SubCriterion::factory()->direct()->for($main, 'mainCriterion')->create([
        'code' => $code,
        'sort_order' => $order,
        'weight' => Weight::of($weight),
    ]);
}

function pointIn(SubCriterion $sub, string $code, string $weight, int $order = 1): GuidingPoint
{
    return GuidingPoint::factory()->for($sub, 'subCriterion')->create([
        'code' => $code,
        'sort_order' => $order,
        'weight' => Weight::of($weight),
    ]);
}

function balancedDraft(): CriteriaVersion
{
    $version = draftVersion();
    $main = mainIn($version, 'M1', '1000.00');
    $scored = subIn($main, 'M1.1', '600.00');
    pointIn($scored, 'M1.1.1', '300.00');
    pointIn($scored, 'M1.1.2', '300.00', 2);
    directSubIn($main, 'M1.2', '400.00', 2);

    return $version->refresh();
}

function mainOf(CriteriaVersion $version, string $code): MainCriterion
{
    return MainCriterion::query()->where('criteria_version_id', $version->id)->where('code', $code)->firstOrFail();
}

function subOf(CriteriaVersion $version, string $code): SubCriterion
{
    return SubCriterion::query()->whereHas('mainCriterion', static fn ($query) => $query->where('criteria_version_id', $version->id))->where('code', $code)->firstOrFail();
}

function pointOf(CriteriaVersion $version, string $code): GuidingPoint
{
    return GuidingPoint::query()->whereHas('subCriterion.mainCriterion', static fn ($query) => $query->where('criteria_version_id', $version->id))->where('code', $code)->firstOrFail();
}

function installedSeedCriteria(): CriteriaVersion
{
    $version = app(InstallCriteriaSeed::class)->handle(database_path(InstallCriteriaSeedCommand::SEED_PATH));

    return $version instanceof CriteriaVersion ? $version->refresh() : throw new LogicException('The criteria seed was not installed.');
}
