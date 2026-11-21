<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\ProfileSection;
use App\Models\Audit\Audit;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChange;
use Database\Factories\Entities\EntityFactory;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

const NAMAA_VAT_NUMBER = '310123456700003';

/**
 * @return array<string, string|bool|null>
 */
function billingDetails(array $overrides = []): array
{
    return [
        'address_building_number' => '2154',
        'address_street' => 'شارع الأمير سلطان',
        'address_district' => 'حي الروضة',
        'address_city' => 'جدة',
        'address_postal_code' => '23435',
        'address_additional_number' => '8027',
        'address_short_code' => 'jrda2154',
        'vat_registered' => true,
        'vat_number' => NAMAA_VAT_NUMBER,
        ...$overrides,
    ];
}

function entityWithoutBilling(): Entity
{
    return entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $factory->withoutBilling());
}

it('saves the billing section, stores the short address in upper case and records the history', function (): void {
    $entity = entityWithoutBilling();

    saveSection($entity, ProfileSection::Billing, billingDetails())
        ->assertOk()
        ->assertJsonPath('meta.section', 'billing')
        ->assertJsonPath('data.address_building_number', '2154')
        ->assertJsonPath('data.address_city', 'جدة')
        ->assertJsonPath('data.address_short_code', 'JRDA2154')
        ->assertJsonPath('data.vat_registered', true)
        ->assertJsonPath('data.vat_number', NAMAA_VAT_NUMBER)
        ->assertJsonPath('data.vat_certificate', null)
        ->assertJsonPath('data.billing_complete', false)
        ->assertJsonPath('data.profile_completion.sections.4', ['key' => 'billing', 'state' => 'incomplete', 'missing_count' => 1, 'missing_fields' => ['vat_certificate']]);

    $history = EntityProfileChange::query()->where('entity_id', $entity->id)->pluck('field')->map->value->all();

    expect($history)->toContain('address_building_number', 'address_short_code', 'vat_registered', 'vat_number')
        ->and(Audit::query()->where('event', AuditEvent::EntityProfileUpdated->value)->sole()->new_values)->toMatchArray(['section' => 'billing', 'vat_number' => NAMAA_VAT_NUMBER]);
});

it('lists the billing section with its fields in the profile catalog', function (): void {
    $response = switchTo(ownerOf(entityWithOwner()))->getJson('/api/v1/entities/me')->assertOk();

    expect($response->json('data.profile_sections.4.key'))->toBe('billing')
        ->and(array_column($response->json('data.profile_sections.4.fields'), 'requirement', 'key'))->toBe([
            'address_building_number' => 'required',
            'address_street' => 'required',
            'address_district' => 'required',
            'address_city' => 'required',
            'address_postal_code' => 'required',
            'address_additional_number' => 'required',
            'address_short_code' => 'optional',
            'vat_registered' => 'required',
            'vat_number' => 'required',
            'vat_certificate' => 'required',
        ]);
});

it('completes the billing section without VAT data when the entity is not registered', function (): void {
    $entity = entityWithoutBilling();

    saveSection($entity, ProfileSection::Billing, billingDetails(['vat_registered' => false, 'vat_number' => null, 'address_short_code' => null]))
        ->assertOk()
        ->assertJsonPath('data.billing_complete', true)
        ->assertJsonPath('data.profile_completion.percent', 100)
        ->assertJsonPath('data.profile_completion.counted', 22)
        ->assertJsonPath('data.profile_completed', true);
});

it('counts the VAT number and certificate only after a «yes» answer', function (): void {
    $entity = entityWithoutBilling();
    $owner = ownerOf($entity);

    switchTo($owner)->getJson('/api/v1/entities/me')->assertOk()
        ->assertJsonPath('data.profile_completion.counted', 22)
        ->assertJsonPath('data.profile_completion.completed_count', 15)
        ->assertJsonPath('data.profile_completion.percent', 68)
        ->assertJsonPath('data.profile_completed', false);

    saveSection($entity, ProfileSection::Billing, billingDetails())->assertOk()
        ->assertJsonPath('data.profile_completion.counted', 24)
        ->assertJsonPath('data.profile_completion.completed_count', 23)
        ->assertJsonPath('data.profile_completion.percent', 95);
});

it('refuses each malformed billing value', function (string $field, mixed $value): void {
    $entity = entityWithoutBilling();

    saveSection($entity, ProfileSection::Billing, billingDetails([$field => $value]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect($entity->refresh()->address_building_number)->toBeNull();
})->with([
    'building number of 3 digits' => ['address_building_number', '215'],
    'building number with letters' => ['address_building_number', '21A4'],
    'building number in Arabic-Indic digits' => ['address_building_number', '٢١٥٤'],
    'missing street' => ['address_street', null],
    'missing district' => ['address_district', ''],
    'missing city' => ['address_city', null],
    'postal code of 4 digits' => ['address_postal_code', '2343'],
    'postal code of 6 digits' => ['address_postal_code', '234350'],
    'additional number of 5 digits' => ['address_additional_number', '80271'],
    'short address with digits first' => ['address_short_code', '2154JRDA'],
    'short address too long' => ['address_short_code', 'JRDAA2154'],
    'unanswered VAT question' => ['vat_registered', null],
    'VAT number not starting with 3' => ['vat_number', '210123456700003'],
    'VAT number not ending with 3' => ['vat_number', '310123456700002'],
    'VAT number of 14 digits' => ['vat_number', '31012345670003'],
]);

it('asks for the VAT number after a «yes» and refuses it after a «no»', function (): void {
    $entity = entityWithoutBilling();

    saveSection($entity, ProfileSection::Billing, billingDetails(['vat_number' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['vat_number' => __('validation.custom.vat_number.required_if_accepted')]);

    saveSection($entity, ProfileSection::Billing, billingDetails(['vat_registered' => false]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['vat_number' => __('validation.custom.vat_number.prohibited_if_declined')]);
});

it('requires every billing key to be sent', function (): void {
    $entity = entityWithoutBilling();
    $body = sectionBody($entity, ProfileSection::Billing, billingDetails());
    unset($body['address_short_code']);

    switchTo(ownerOf($entity))->patchJson('/api/v1/entities/me/profile/billing', $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address_short_code']);
});

it('rejects a stale billing save', function (): void {
    $entity = entityWithoutBilling();
    $body = sectionBody($entity, ProfileSection::Billing, billingDetails());

    saveSection($entity, ProfileSection::Billing, billingDetails(['vat_registered' => false, 'vat_number' => null]))->assertOk();

    switchTo(ownerOf($entity))->patchJson('/api/v1/entities/me/profile/billing', $body)
        ->assertConflict()
        ->assertJsonPath('code', 'profile.section_stale');
});

it('keeps staff and guests away from the own billing section', function (): void {
    $entity = entityWithoutBilling();

    saveSection($entity, ProfileSection::Billing, billingDetails(), systemAdmin())->assertForbidden();
    test()->flushSession();
    app('auth')->forgetGuards();
    test()->patchJson('/api/v1/entities/me/profile/billing', billingDetails())->assertUnauthorized();

    expect($entity->refresh()->address_building_number)->toBeNull();
});

it('writes only to the signed-in entity', function (): void {
    $entity = entityWithoutBilling();
    $other = entityWithoutBilling();

    saveSection($entity, ProfileSection::Billing, billingDetails(), ownerOf($other))->assertOk();

    expect($entity->refresh()->address_building_number)->toBeNull()
        ->and($other->refresh()->address_building_number)->toBe('2154');
});

it('shows the billing data read-only on the staff entity record', function (): void {
    $entity = entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $factory->vatRegistered());

    switchTo(systemAdmin())->getJson('/api/v1/entities/'.$entity->id)->assertOk()
        ->assertJsonPath('data.address_postal_code', '12214')
        ->assertJsonPath('data.vat_registered', true)
        ->assertJsonPath('data.vat_number', NAMAA_VAT_NUMBER)
        ->assertJsonPath('data.vat_certificate', null)
        ->assertJsonPath('data.billing_complete', false);
});
