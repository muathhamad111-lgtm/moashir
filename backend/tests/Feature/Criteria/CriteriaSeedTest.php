<?php

declare(strict_types=1);

use App\Enums\Criteria\CriteriaVersionStatus;
use App\Enums\Criteria\CriterionGroup;
use App\Enums\Criteria\EvaluationType;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Services\Criteria\CriteriaConsistencyChecker;
use App\Services\Criteria\CriteriaTreeLoader;
use Database\Seeders\CriteriaSeeder;

it('installs the available criteria content as published version 1', function (): void {
    $version = installedSeedCriteria();
    $mains = MainCriterion::query()->where('criteria_version_id', $version->id)->orderBy('sort_order')->get();

    expect($version->version_number)->toBe(1)
        ->and($version->status)->toBe(CriteriaVersionStatus::Published)
        ->and($version->published_by_user_id)->toBeNull()
        ->and($version->label?->en)->toBe('Institutional Excellence Model - Version 1')
        ->and($mains)->toHaveCount(9)
        ->and($mains->pluck('code')->all())->toBe(['M1', 'M2', 'M3', 'M4', 'M5', 'M6', 'M7', 'M8', 'M9'])
        ->and($mains->filter(static fn (MainCriterion $main): bool => $main->group === CriterionGroup::Enablers))->toHaveCount(5)
        ->and($mains->filter(static fn (MainCriterion $main): bool => $main->group === CriterionGroup::CreativityLearning))->toHaveCount(1)
        ->and($mains->filter(static fn (MainCriterion $main): bool => $main->group === CriterionGroup::Results))->toHaveCount(3)
        ->and(app(CriteriaConsistencyChecker::class)->check(app(CriteriaTreeLoader::class)->load($version))->isConsistent())->toBeTrue();
});

it('seeds bilingual content with Western digits only', function (): void {
    $version = installedSeedCriteria();
    $subs = SubCriterion::query()->whereHas('mainCriterion', static fn ($query) => $query->where('criteria_version_id', $version->id))->get();
    $points = GuidingPoint::query()->whereIn('sub_criterion_id', $subs->modelKeys())->get();

    expect($subs)->toHaveCount(19)
        ->and($subs->filter(static fn (SubCriterion $sub): bool => $sub->evaluation_type === EvaluationType::GuidingPoints))->toHaveCount(7)
        ->and($points)->toHaveCount(14)
        ->and($subs->every(static fn (SubCriterion $sub): bool => $sub->name->ar !== '' && $sub->name->en !== ''))->toBeTrue()
        ->and((string) file_get_contents(database_path('data/criteria/version-1.json')))->not->toMatch('/[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u');
});

it('installs the seed only once', function (): void {
    installedSeedCriteria();

    $this->artisan('moashir:criteria:install-seed')
        ->expectsOutputToContain('Skipped: a criteria version already exists.')
        ->assertSuccessful();

    expect(CriteriaVersion::query()->count())->toBe(1);
});

it('installs the seed from the console command', function (): void {
    $this->artisan('moashir:criteria:install-seed')
        ->expectsOutputToContain('Criteria version 1 was installed and published with 9 main criteria.')
        ->assertSuccessful();

    expect(CriteriaVersion::query()->published()->count())->toBe(1);
});

it('runs the criteria seeder as part of local seeding', function (): void {
    $this->seed(CriteriaSeeder::class);
    $this->seed(CriteriaSeeder::class);

    expect(CriteriaVersion::query()->count())->toBe(1);
});
