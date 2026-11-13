<?php

declare(strict_types=1);

use App\Enums\Entities\ProfileSection;
use Database\Factories\Entities\EntityFactory;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @param  array<string, list<string>>  $missing
 * @return list<array{key: string, state: string, missing_count: int, missing_fields: list<string>}>
 */
function completionSections(array $missing = []): array
{
    return array_map(static fn (ProfileSection $section): array => [
        'key' => $section->value,
        'state' => ($missing[$section->value] ?? []) === [] ? 'complete' : 'incomplete',
        'missing_count' => count($missing[$section->value] ?? []),
        'missing_fields' => $missing[$section->value] ?? [],
    ], ProfileSection::cases());
}

it('reports a complete profile at 100 percent without the optional phone, website and logo', function (): void {
    $owner = ownerOf(entityWithOwner());

    switchTo($owner)->getJson('/api/v1/entities/me')->assertOk()
        ->assertJsonPath('data.profile_completed', true)
        ->assertJsonPath('data.phone', null)
        ->assertJsonPath('data.logo', null)
        ->assertJsonPath('data.profile_completion', ['percent' => 100, 'counted' => 15, 'completed_count' => 15, 'sections' => completionSections()]);
});

it('reports the design mock at 60 percent by section', function (): void {
    $entity = entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $factory->incompleteProfile()->state([
        'unified_number' => null, 'license_number' => null, 'founded_on' => null, 'founding_year' => 2015,
    ]));

    switchTo(ownerOf($entity))->getJson('/api/v1/entities/me')->assertOk()
        ->assertJsonPath('data.profile_completed', false)
        ->assertJsonPath('data.founding_year', 2015)
        ->assertJsonPath('data.profile_completion', [
            'percent' => 60,
            'counted' => 15,
            'completed_count' => 9,
            'sections' => completionSections([
                'identity' => ['unified_number', 'license_number', 'founded_on'],
                'governance' => ['governance_grade', 'employees_count', 'annual_beneficiaries_count'],
            ]),
        ]);
});

it('follows each section save and keeps the completion timestamp on 100 percent only', function (): void {
    $entity = entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $factory->incompleteProfile());

    saveSection($entity, ProfileSection::Governance, ['governance_grade' => '82.50'])->assertOk()
        ->assertJsonPath('data.profile_completion.percent', 86)
        ->assertJsonPath('data.profile_completed', false);
    expect($entity->refresh()->profile_completed_at)->toBeNull();

    saveSection($entity, ProfileSection::Governance, ['employees_count' => 14, 'annual_beneficiaries_count' => 3200])->assertOk()
        ->assertJsonPath('data.profile_completion.percent', 100)
        ->assertJsonPath('data.profile_completed', true);
    expect($entity->refresh()->profile_completed_at)->not->toBeNull();

    saveSection($entity, ProfileSection::Contact, ['phone' => null, 'website_url' => null])->assertOk()
        ->assertJsonPath('data.profile_completion.percent', 100);

    saveSection($entity, ProfileSection::Identity, ['license_number' => null])->assertOk()
        ->assertJsonPath('data.profile_completion.percent', 93)
        ->assertJsonPath('data.profile_completion.sections.0.missing_fields', ['license_number']);
    expect($entity->refresh()->profile_completed_at)->toBeNull();
});
