<?php

declare(strict_types=1);

use App\Actions\Entities\RegisterEntity;
use App\Data\Entities\EntityContactData;
use App\Data\Entities\EntityProfileData;
use App\Data\Entities\EntityRegistrationData;
use App\Data\Entities\EntityRegistryUpdate;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Identity\Locale;
use App\Models\Entities\Entity;
use App\Services\Entities\EntityEraser;
use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\UnifiedNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function ownProfileWithNumber(Entity $entity, array $extra = []): array
{
    return [...profilePayload($entity, ['founding_year' => $entity->founding_year]), 'marketing_consent' => false, ...$extra];
}

it('requires a valid unified national number at registration', function (array $override, string $message): void {
    $this->postJson('/api/v1/auth/register', registrationPayload($override), ['Accept-Language' => 'en'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.unified_number.0', $message);

    expect(Entity::query()->count())->toBe(0);
})->with([
    'missing' => [['unified_number' => null], 'The unified national number field is required.'],
    'nine digits' => [['unified_number' => '700123456'], 'The unified national number must contain exactly 10 digits (0-9) and start with 7.'],
    'starts with 1' => [['unified_number' => '1001234567'], 'The unified national number must contain exactly 10 digits (0-9) and start with 7.'],
    'letters' => [['unified_number' => '70012345AB'], 'The unified national number must contain exactly 10 digits (0-9) and start with 7.'],
    'Arabic-Indic digits' => [['unified_number' => '٧٠٠١٢٣٤٥٦٧'], 'The unified national number must contain exactly 10 digits (0-9) and start with 7.'],
]);

it('refuses a unified national number already registered for another entity with a friendly message', function (): void {
    entityWithOwner(static fn ($factory) => $factory->state(['unified_number' => '7001234567']));

    $this->postJson('/api/v1/auth/register', registrationPayload())
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('errors.unified_number.0', 'هذا الرقم الوطني الموحد مسجّل لجهة أخرى في المنصة. تحقّق من الرقم أو تواصل مع فريق المنصة.');
});

it('turns a unified national number taken by a concurrent registration into the same validation error', function (): void {
    $existing = entityWithOwner(static fn ($factory) => $factory->state(['unified_number' => '7001234567']));
    $profile = new EntityProfileData(
        $existing->name, $existing->entity_type, $existing->development_sector, $existing->org_size, $existing->founding_year, $existing->region_id,
        $existing->city_id, null, null, null, false,
        new EntityRegistryUpdate(provided: [EntityProfileField::UnifiedNumber], unifiedNumber: new UnifiedNumber('7001234567')),
    );
    publishTerms();

    expect(fn () => app(RegisterEntity::class)->handle(new EntityRegistrationData(
        $profile,
        new EntityContactData('سلمان بن فهد العتيبي', null),
        new EmailAddress('race@alamal.example.sa'),
        'Wadi-Hanifa-Spring-2026',
        [],
        false,
        Locale::Arabic,
    )))->toThrow(ValidationException::class, __('validation.custom.unified_number.unique'));
});

it('keeps an existing number when an older client omits it and updates it with a history entry', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['unified_number' => '7001111111']));
    $owner = ownerOf($entity);

    switchTo($owner)->putJson('/api/v1/entities/me', ownProfileWithNumber($entity))->assertOk()->assertJsonPath('data.unified_number', '7001111111');
    switchTo($owner)->putJson('/api/v1/entities/me', ownProfileWithNumber($entity, ['unified_number' => '7002222222']))->assertOk()
        ->assertJsonPath('data.unified_number', '7002222222');

    $history = collect(switchTo($owner)->getJson('/api/v1/entities/me/profile-changes')->assertOk()->json('data'))->keyBy('field');
    expect($history->get('unified_number'))->toMatchArray([
        'old_value' => '7001111111',
        'new_value' => '7002222222',
        'field_label' => ['ar' => 'الرقم الوطني الموحد', 'en' => 'Unified national number'],
    ]);
});

it('never lets a profile update clear the mandatory number', function (mixed $value): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['unified_number' => '7001111111']));

    switchTo(ownerOf($entity))->putJson('/api/v1/entities/me', ownProfileWithNumber($entity, ['unified_number' => $value]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unified_number');

    expect($entity->refresh()->unified_number)->toBe('7001111111');
})->with([null, '']);

it('accepts the entity own number and refuses one held by another entity on update', function (): void {
    entityWithOwner(static fn ($factory) => $factory->state(['unified_number' => '7009999999']));
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['unified_number' => '7001111111']));
    $owner = ownerOf($entity);

    switchTo($owner)->putJson('/api/v1/entities/me', ownProfileWithNumber($entity, ['unified_number' => '7001111111']))->assertOk();
    switchTo($owner)->putJson('/api/v1/entities/me', ownProfileWithNumber($entity, ['unified_number' => '7009999999']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.unified_number.0', __('validation.custom.unified_number.unique'));
});

it('nudges an existing entity without the number through a required completion item', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['unified_number' => null]));

    switchTo(ownerOf($entity))->getJson('/api/v1/entities/me')->assertOk()
        ->assertJsonPath('data.unified_number', null)
        ->assertJsonPath('data.profile_completed', false)
        ->assertJsonPath('data.profile_completion.percent', 87)
        ->assertJsonPath('data.profile_completion.items.5', ['key' => 'unified_number', 'is_complete' => false, 'is_required' => true]);
});

it('lets staff read and correct the number with the reason recorded in the history', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['unified_number' => null]));
    $admin = systemAdmin();
    $body = [...profilePayload($entity, ['founding_year' => $entity->founding_year]), 'unified_number' => '7003333333', 'reason' => 'إضافة الرقم الوطني الموحد بعد مطابقته مع سجل المركز الوطني.'];

    switchTo($admin)->putJson("/api/v1/entities/{$entity->id}", $body)->assertOk()->assertJsonPath('data.unified_number', '7003333333');
    switchTo($admin)->getJson("/api/v1/entities/{$entity->id}")->assertOk()->assertJsonPath('data.unified_number', '7003333333');

    expect($entity->refresh()->unified_number)->toBe('7003333333')
        ->and($entity->profileChanges()->where('field', 'unified_number')->sole()->changed_by_user_id)->toBe($admin->id);
});

it('lets the database refuse a malformed or duplicate number written directly', function (string $value): void {
    entityWithOwner(static fn ($factory) => $factory->state(['unified_number' => '7008888888']));
    $entity = entityWithOwner();

    expect(fn () => DB::table('entities')->where('id', $entity->id)->update(['unified_number' => $value]))->toThrow(QueryException::class);
})->with(['8001234567', '700123456', '7008888888']);

it('clears the number when the entity is permanently erased', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['unified_number' => '7004444444']));
    $entity->delete();

    app(EntityEraser::class)->erase($entity->refresh());

    expect(Entity::withTrashed()->findOrFail($entity->id)->unified_number)->toBeNull();
});
