<?php

declare(strict_types=1);

use App\Enums\Entities\ProfileCompletionItem;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @param  list<string>  $missing
 * @return list<array{key: string, is_complete: bool, is_required: bool}>
 */
function completionItems(array $missing): array
{
    return array_map(static fn (ProfileCompletionItem $item): array => [
        'key' => $item->value,
        'is_complete' => ! in_array($item->value, $missing, true),
        'is_required' => $item->isRequired(),
    ], ProfileCompletionItem::cases());
}

it('reports a complete profile at 100 percent without the optional phone, website and logo', function (): void {
    $owner = ownerOf(entityWithOwner());

    switchTo($owner)->getJson('/api/v1/entities/me')->assertOk()
        ->assertJsonPath('data.profile_completed', true)
        ->assertJsonPath('data.profile_completion', ['percent' => 100, 'items' => completionItems(['phone', 'website_url', 'logo'])]);
});

it('reports the share of the required items of an incomplete profile', function (): void {
    $owner = ownerOf(entityWithOwner(static fn ($factory) => $factory->incompleteProfile()));

    switchTo($owner)->getJson('/api/v1/entities/me')->assertOk()
        ->assertJsonPath('data.profile_completed', false)
        ->assertJsonPath('data.profile_completion', [
            'percent' => 62,
            'items' => completionItems(['governance_grade', 'employees_count', 'annual_beneficiaries_count', 'phone', 'website_url', 'logo']),
        ]);
});

it('makes an existing profile without the licence number and the founding date incomplete', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['license_number' => null, 'founded_on' => null]));

    switchTo(ownerOf($entity))->getJson('/api/v1/entities/me')->assertOk()
        ->assertJsonPath('data.profile_completed', false)
        ->assertJsonPath('data.profile_completion.percent', 75)
        ->assertJsonPath('data.license_number', null)
        ->assertJsonPath('data.founded_on', null);
});

it('returns the completion after a profile update and never counts the optional items', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);

    switchTo($owner)->putJson('/api/v1/entities/me', [...profilePayload($entity, ['governance_grade' => null]), 'marketing_consent' => false])->assertOk()
        ->assertJsonPath('data.profile_completed', false)
        ->assertJsonPath('data.profile_completion', ['percent' => 87, 'items' => completionItems(['governance_grade', 'website_url', 'logo'])]);

    switchTo($owner)->putJson('/api/v1/entities/me', [...profilePayload($entity, ['phone' => null]), 'marketing_consent' => false, 'license_number' => null])->assertOk()
        ->assertJsonPath('data.profile_completed', false)
        ->assertJsonPath('data.profile_completion.percent', 87);

    switchTo($owner)->putJson('/api/v1/entities/me', [...profilePayload($entity), 'marketing_consent' => false, 'license_number' => '770000456', 'website_url' => 'https://alamal.example.sa'])->assertOk()
        ->assertJsonPath('data.profile_completed', true)
        ->assertJsonPath('data.profile_completion.percent', 100)
        ->assertJsonPath('data.profile_completion.items.9', ['key' => 'website_url', 'is_complete' => true, 'is_required' => false]);

    expect($entity->refresh()->profile_completed_at)->not->toBeNull();
});
