<?php

declare(strict_types=1);

use App\Actions\Entities\DeactivateEntity;
use App\Actions\Entities\PermanentlyDeleteEntity;
use App\Actions\Entities\RegisterEntity;
use App\Actions\Entities\RestoreEntity;
use App\Actions\Entities\SoftDeleteEntity;
use App\Actions\Entities\VerifyEntityRegistration;
use App\Data\Entities\EntityContactData;
use App\Data\Entities\EntityProfileData;
use App\Data\Entities\EntityRegistrationData;
use App\Data\Entities\PermanentDeletionInstruction;
use App\Data\Entities\SoftDeletionInstruction;
use App\Enums\Entities\DevelopmentSector;
use App\Enums\Entities\EntityStatus;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Enums\Identity\Locale;
use App\Exceptions\Entities\EntityRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\EntityName;
use App\Support\ValueObjects\PhoneNumber;
use Illuminate\Support\Facades\Notification;

function registrationData(string $email): EntityRegistrationData
{
    $city = regionWithCity();

    return new EntityRegistrationData(
        new EntityProfileData(
            new EntityName('مؤسسة النماء الأهلية', 'Al-Namaa Civil Foundation'),
            EntityType::CivilFoundation,
            DevelopmentSector::Educational,
            OrgSize::Small,
            2015,
            $city->region_id,
            $city->id,
            null,
            null,
            null,
            false,
        ),
        new EntityContactData('نورة بنت فهد العتيبي', new PhoneNumber('+966561234567')),
        new EmailAddress($email),
        'Wadi-Hanifa-Spring-2026',
        [publishTerms()->id],
        true,
        Locale::English,
    );
}

beforeEach(function (): void {
    Notification::fake();
});

it('creates the entity, its single owner and the consent records', function (): void {
    $challenge = app(RegisterEntity::class)->handle(registrationData('contact@alnamaa.example.sa'));
    $owner = User::query()->where('email', 'contact@alnamaa.example.sa')->firstOrFail();
    $entity = Entity::query()->findOrFail($owner->entity_id);

    expect($challenge->challengeId)->toBeString()
        ->and($entity->status)->toBe(EntityStatus::PendingVerification)
        ->and($entity->marketing_consent_at)->not->toBeNull()
        ->and($entity->profile_completed_at)->toBeNull()
        ->and($owner->locale)->toBe(Locale::English)
        ->and($owner->termsAcceptances()->count())->toBe(1)
        ->and(User::query()->where('entity_id', $entity->id)->count())->toBe(1);
});

it('returns a decoy challenge for an address that is already registered', function (): void {
    User::factory()->create(['email' => 'contact@alnamaa.example.sa']);

    $challenge = app(RegisterEntity::class)->handle(registrationData('contact@alnamaa.example.sa'));

    expect($challenge->challengeId)->toBeString()
        ->and(Entity::query()->count())->toBe(0);
});

it('verifies a pending registration once and ignores later calls', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingVerification());
    $owner = ownerOf($entity);

    app(VerifyEntityRegistration::class)->handle($owner);
    $verifiedAt = $entity->refresh()->verified_at;
    $this->travel(5)->minutes();
    app(VerifyEntityRegistration::class)->handle($owner);

    expect($entity->refresh()->status)->toBe(EntityStatus::Active)
        ->and($entity->verified_at?->equalTo($verifiedAt))->toBeTrue();
});

it('deactivates only an active entity', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingVerification());

    expect(fn () => app(DeactivateEntity::class)->handle(systemAdmin(), $entity, 'سبب موثق للإيقاف.'))->toThrow(IllegalStateTransition::class);
});

it('runs the soft delete, restore and permanent deletion pipeline in order', function (): void {
    $actor = systemAdmin();
    $entity = entityWithOwner();

    app(SoftDeleteEntity::class)->handle($actor, $entity, new SoftDeletionInstruction('طلب الجهة إغلاق الحساب.'));
    expect($entity->refresh()->trashed())->toBeTrue();

    app(RestoreEntity::class)->handle($actor, $entity, 'تراجعت الجهة عن الإغلاق.');
    expect($entity->refresh()->trashed())->toBeFalse();

    expect(fn () => app(PermanentlyDeleteEntity::class)->handle($entity, new PermanentDeletionInstruction('حذف نهائي معتمد من الجهة المالكة.', $entity->reference(), true)))
        ->toThrow(EntityRejected::class);

    app(SoftDeleteEntity::class)->handle($actor, $entity, new SoftDeletionInstruction('طلب الجهة إغلاق الحساب نهائيًا.'));
    $outcome = app(PermanentlyDeleteEntity::class)->handle($entity->refresh(), new PermanentDeletionInstruction('حذف نهائي معتمد من الجهة المالكة.', $entity->reference(), true));

    expect($outcome->reference)->toBe($entity->reference())
        ->and(Entity::withTrashed()->findOrFail($entity->id)->isErased())->toBeTrue();
});
