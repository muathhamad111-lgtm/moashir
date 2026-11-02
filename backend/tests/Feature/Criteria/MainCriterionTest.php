<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Notifications\Criteria\CriteriaNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @return array<string, mixed>
 */
function mainPayload(array $overrides = []): array
{
    return [
        'name' => ['ar' => 'الابتكار وإدارة المعرفة', 'en' => 'Innovation and Knowledge Management'],
        'description' => ['ar' => 'تبني الابتكار والتعلم المؤسسي.', 'en' => 'Adopting innovation and organisational learning.'],
        'weight' => '600',
        'group' => 'creativity_learning',
        ...$overrides,
    ];
}

it('lists the main criteria of the draft with their weight summary', function (): void {
    $draft = balancedDraft();
    signIn(criteriaOfficer());

    $this->getJson('/api/v1/criteria/mains')
        ->assertOk()
        ->assertJsonPath('meta.version.id', $draft->id)
        ->assertJsonPath('meta.version.status', 'draft')
        ->assertJsonPath('data.0.code', 'M1')
        ->assertJsonPath('data.0.weight', '1000.00')
        ->assertJsonPath('data.0.sub_count', 2)
        ->assertJsonPath('data.0.allocated_weight', '1000.00')
        ->assertJsonPath('data.0.remaining_weight', '0.00')
        ->assertJsonPath('data.0.weight_balanced', true)
        ->assertJsonPath('meta.pagination.total', 1);
});

it('falls back to the published version and accepts an explicit version', function (): void {
    $published = publishVersion(balancedDraft());
    $other = draftVersion();
    mainIn($other, 'M7', '300.00');
    signIn(criteriaOfficer());

    $this->getJson('/api/v1/criteria/mains')->assertOk()->assertJsonPath('meta.version.id', $other->id);
    $other->delete();
    $this->getJson('/api/v1/criteria/mains')->assertOk()->assertJsonPath('meta.version.id', $published->id);
    $this->getJson('/api/v1/criteria/mains?version='.$published->id)->assertOk()->assertJsonPath('data.0.code', 'M1');
});

it('answers an empty list when no criteria version exists', function (): void {
    signIn(criteriaOfficer());

    $this->getJson('/api/v1/criteria/mains')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.version', null);
});

it('searches Arabic names with normalization, filters and sorts', function (): void {
    $draft = draftVersion();
    mainIn($draft, 'M1', '1000.00')->forceFill(['name' => ['ar' => 'الإدارة الإستراتيجية', 'en' => 'Strategic Management']])->save();
    mainIn($draft, 'M2', '400.00', 2)->forceFill(['name' => ['ar' => 'نتائج المستفيدين', 'en' => 'Beneficiary Results'], 'is_active' => false])->save();
    signIn(criteriaOfficer());

    $this->getJson('/api/v1/criteria/mains?search='.urlencode('الاستراتيجيه'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'M1');
    $this->getJson('/api/v1/criteria/mains?search=beneficiary')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'M2');
    $this->getJson('/api/v1/criteria/mains?filter[active]=false')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.active', false);
    $this->getJson('/api/v1/criteria/mains?sort=weight')->assertOk()->assertJsonPath('data.0.code', 'M2');
    $this->withHeader('Accept-Language', 'en')->getJson('/api/v1/criteria/mains?sort=name')->assertOk()->assertJsonPath('data.0.code', 'M2');
});

it('validates the main criteria list query', function (string $query, string $field): void {
    signIn(criteriaOfficer());

    $this->getJson('/api/v1/criteria/mains?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'unknown sort' => ['sort=code', 'sort'],
    'non boolean active filter' => ['filter[active]=yes', 'filter.active'],
    'non numeric version' => ['version=current', 'version'],
]);

it('creates a main criterion at the end of the draft and notifies the other criteria staff', function (): void {
    $draft = balancedDraft();
    $actor = criteriaOfficer();
    $colleague = criteriaOfficer();
    $administrator = systemAdmin();
    signIn($actor);

    $this->postJson('/api/v1/criteria/mains', mainPayload())
        ->assertCreated()
        ->assertJsonPath('data.code', 'M2')
        ->assertJsonPath('data.version_id', $draft->id)
        ->assertJsonPath('data.order', 2)
        ->assertJsonPath('data.weight', '600.00')
        ->assertJsonPath('data.group', 'creativity_learning')
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('data.remaining_weight', '600.00')
        ->assertJsonPath('data.weight_balanced', false);

    Notification::assertSentTo([$colleague, $administrator], CriteriaNotification::class, static fn (CriteriaNotification $notification): bool => $notification->notice->code->value === 'STD-01'
        && str_contains($notification->notice->body->ar, 'الابتكار وإدارة المعرفة'));
    Notification::assertNotSentTo($actor, CriteriaNotification::class, static fn (CriteriaNotification $notification): bool => $notification->notice->code->value === 'STD-01');
    expect(Audit::query()->where('event', AuditEvent::CriteriaMainCreated->value)->where('user_id', $actor->id)->exists())->toBeTrue();
});

it('raises STD-07 once when an edit makes the draft inconsistent', function (): void {
    balancedDraft();
    $colleague = criteriaOfficer();
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/mains', mainPayload())->assertCreated();
    $this->postJson('/api/v1/criteria/mains', mainPayload(['weight' => '200']))->assertCreated();

    Notification::assertSentToTimes($colleague, CriteriaNotification::class, 3);
    Notification::assertSentTo($colleague, CriteriaNotification::class, static fn (CriteriaNotification $notification): bool => $notification->notice->code->value === 'STD-07');
});

it('refuses tree edits when there is no draft', function (): void {
    publishVersion(balancedDraft());
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/mains', mainPayload())->assertConflict()->assertJsonPath('code', 'criteria.no_draft');
});

it('refuses structural edits on a published version', function (): void {
    $published = publishVersion(balancedDraft());
    $main = mainOf($published, 'M1');
    signIn(criteriaOfficer());

    $this->putJson('/api/v1/criteria/mains/'.$main->id, ['weight' => '900'])
        ->assertConflict()
        ->assertJsonPath('code', 'criteria.version_not_draft')
        ->assertJsonPath('errors.version_status', ['published']);
    $this->patchJson('/api/v1/criteria/mains/'.$main->id.'/active', ['active' => false])->assertConflict();
    $this->deleteJson('/api/v1/criteria/mains/'.$main->id)->assertConflict();

    expect($main->refresh()->weight->toDecimal())->toBe('1000.00');
});

it('validates the main criterion payload', function (array $overrides, string $field): void {
    balancedDraft();
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/mains', mainPayload($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'missing English name' => [['name' => ['ar' => 'الابتكار']], 'name.en'],
    'unknown name language' => [['name' => ['ar' => 'الابتكار', 'en' => 'Innovation', 'fr' => 'Innovation']], 'name'],
    'zero weight' => [['weight' => '0'], 'weight'],
    'three decimals' => [['weight' => '10.555'], 'weight'],
    'weight above the limit' => [['weight' => '100000.01'], 'weight'],
    'text weight' => [['weight' => 'heavy'], 'weight'],
    'unknown group' => [['group' => 'innovation'], 'group'],
    'non boolean active' => [['active' => 'maybe'], 'active'],
]);

it('blocks lowering a main weight below the weight allocated to its sub-criteria', function (): void {
    $draft = balancedDraft();
    signIn(criteriaOfficer());

    $this->putJson('/api/v1/criteria/mains/'.mainOf($draft, 'M1')->id, ['weight' => '900'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.weight_below_allocated')
        ->assertJsonPath('meta.allocated_weight', '1000.00')
        ->assertJsonValidationErrors('weight');
});

it('updates a main criterion, audits before and after, and raises STD-02 on a weight change', function (): void {
    $draft = balancedDraft();
    $main = mainOf($draft, 'M1');
    $colleague = criteriaOfficer();
    signIn(criteriaOfficer());

    $this->putJson('/api/v1/criteria/mains/'.$main->id, ['weight' => '1200', 'name' => ['ar' => 'القيادة', 'en' => 'Leadership']])
        ->assertOk()
        ->assertJsonPath('data.weight', '1200.00')
        ->assertJsonPath('data.name.en', 'Leadership')
        ->assertJsonPath('data.description.en', 'Leadership ability to steer the organisation.')
        ->assertJsonPath('data.remaining_weight', '200.00');

    $audit = Audit::query()->where('event', AuditEvent::CriteriaMainUpdated->value)->firstOrFail();
    expect($audit->old_values['weight'] ?? null)->toBe('1000.00')
        ->and($audit->new_values['weight'] ?? null)->toBe('1200.00');
    Notification::assertSentTo($colleague, CriteriaNotification::class, static fn (CriteriaNotification $notification): bool => $notification->notice->code->value === 'STD-02'
        && str_contains($notification->notice->body->en, '1000.00') && str_contains($notification->notice->body->en, '1200.00'));
});

it('clears an optional description with null', function (): void {
    $main = mainOf(balancedDraft(), 'M1');
    signIn(criteriaOfficer());

    $this->putJson('/api/v1/criteria/mains/'.$main->id, ['description' => null])->assertOk()->assertJsonPath('data.description', null);
});

it('distributes a main weight equally over its active sub-criteria', function (): void {
    $draft = draftVersion();
    $main = mainIn($draft, 'M1', '1000.00');
    directSubIn($main, 'M1.1', '100.00');
    directSubIn($main, 'M1.2', '100.00', 2);
    directSubIn($main, 'M1.3', '100.00', 3);
    signIn(criteriaOfficer());

    $this->putJson('/api/v1/criteria/mains/'.$main->id, ['distribute_equally' => true])
        ->assertOk()
        ->assertJsonPath('data.weight_balanced', true);

    expect(SubCriterion::query()->where('main_criterion_id', $main->id)->orderBy('sort_order')->get()->map(static fn (SubCriterion $sub): string => $sub->weight->toDecimal())->all())
        ->toBe(['333.34', '333.33', '333.33']);
});

it('toggles a main criterion and audits it', function (): void {
    $main = mainOf(balancedDraft(), 'M1');
    signIn(criteriaOfficer());

    $this->patchJson('/api/v1/criteria/mains/'.$main->id.'/active', ['active' => false])->assertOk()->assertJsonPath('data.active', false);
    $this->patchJson('/api/v1/criteria/mains/'.$main->id.'/active', [])->assertUnprocessable()->assertJsonValidationErrors('active');

    expect(Audit::query()->where('event', AuditEvent::CriteriaMainToggled->value)->count())->toBe(1);
});

it('reorders the main criteria of the draft as a complete permutation', function (): void {
    $draft = draftVersion();
    $first = mainIn($draft, 'M1', '100.00');
    $second = mainIn($draft, 'M2', '100.00', 2);
    $third = mainIn($draft, 'M3', '100.00', 3);
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/mains/reorder', ['ordered_ids' => [$third->id, $first->id, $second->id]])
        ->assertOk()
        ->assertJsonPath('data.0.code', 'M3')
        ->assertJsonPath('data.0.order', 1)
        ->assertJsonPath('data.2.code', 'M2');

    DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    expect(MainCriterion::query()->where('criteria_version_id', $draft->id)->orderBy('sort_order')->pluck('code')->all())->toBe(['M3', 'M1', 'M2']);

    $this->postJson('/api/v1/criteria/mains/reorder', ['ordered_ids' => [$third->id, $first->id]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'order.incomplete');
    $this->postJson('/api/v1/criteria/mains/reorder', ['ordered_ids' => [$third->id, $third->id, $second->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ordered_ids.0');
});

it('removes a main criterion with its tree and closes the gap in the order', function (): void {
    $draft = balancedDraft();
    $second = mainIn($draft, 'M2', '100.00', 2);
    $removed = mainOf($draft, 'M1');
    signIn(criteriaOfficer());

    $this->deleteJson('/api/v1/criteria/mains/'.$removed->id)->assertNoContent();

    expect(MainCriterion::query()->whereKey($removed->id)->exists())->toBeFalse()
        ->and(SubCriterion::query()->where('main_criterion_id', $removed->id)->exists())->toBeFalse()
        ->and($second->refresh()->sort_order)->toBe(1);
    $audit = Audit::query()->where('event', AuditEvent::CriteriaMainRemoved->value)->firstOrFail();
    expect($audit->old_values['code'] ?? null)->toBe('M1');
});

it('shows a main criterion with its sub-criteria', function (): void {
    $draft = balancedDraft();
    signIn(staffUser(RoleKey::SystemAdmin));

    $this->getJson('/api/v1/criteria/mains/'.mainOf($draft, 'M1')->id)
        ->assertOk()
        ->assertJsonPath('data.main.code', 'M1')
        ->assertJsonPath('data.subs.0.code', 'M1.1')
        ->assertJsonPath('data.subs.0.point_count', 2)
        ->assertJsonPath('data.subs.1.evaluation_type', 'direct')
        ->assertJsonPath('data.subs.1.available_weight', '400.00');
});

it('denies main criterion writes to roles without criteria.manage', function (RoleKey $role): void {
    $main = mainOf(balancedDraft(), 'M1');
    signIn(staffUser($role));

    $this->postJson('/api/v1/criteria/mains', mainPayload())->assertForbidden();
    $this->putJson('/api/v1/criteria/mains/'.$main->id, ['weight' => '1000'])->assertForbidden();
    $this->patchJson('/api/v1/criteria/mains/'.$main->id.'/active', ['active' => false])->assertForbidden();
    $this->postJson('/api/v1/criteria/mains/reorder', ['ordered_ids' => [$main->id]])->assertForbidden();
    $this->deleteJson('/api/v1/criteria/mains/'.$main->id)->assertForbidden();
})->with([
    'system administrator' => [RoleKey::SystemAdmin],
    'assessor' => [RoleKey::Assessor],
    'content officer' => [RoleKey::ContentOfficer],
]);

it('lets the Super Admin manage the draft tree', function (): void {
    balancedDraft();
    signIn(superAdmin());

    $this->postJson('/api/v1/criteria/mains', mainPayload())->assertCreated();
});
