<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Criteria\CriteriaEditKind;
use App\Enums\Criteria\CriteriaEditScope;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Criteria\CriteriaEditApplied;
use App\Models\Audit\Audit;
use App\Models\Criteria\AttachmentRequirement;
use App\Models\Criteria\CriteriaEditEvent;
use App\Notifications\Criteria\CriteriaNotification;
use App\Services\Criteria\PublishedCriteriaTree;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('corrects texts of a published version in place and records a text correction', function (): void {
    $published = installedSeedCriteria();
    $main = mainOf($published, 'M1');
    $sub = subOf($published, 'M1.2');
    $point = pointOf($published, 'M1.1.1');
    $attachment = AttachmentRequirement::query()->where('sub_criterion_id', $sub->id)->orderBy('sort_order')->firstOrFail();
    $administrator = systemAdmin();
    app(PublishedCriteriaTree::class)->current();
    signIn(criteriaOfficer());

    $response = $this->patchJson('/api/v1/criteria/versions/'.$published->id.'/labels', [
        'label' => ['ar' => 'نموذج التميز المؤسسي المعتمد', 'en' => 'Approved Institutional Excellence Model'],
        'nodes' => [
            ['type' => 'main', 'id' => $main->id, 'name' => ['ar' => 'القيادة المؤسسية', 'en' => 'Institutional Leadership']],
            ['type' => 'sub', 'id' => $sub->id, 'description' => null, 'assessor_note_label' => ['ar' => 'صف مستوى الالتزام بالحوكمة', 'en' => 'Describe the governance compliance level']],
            ['type' => 'point', 'id' => $point->id, 'description' => ['ar' => 'وثيقة رؤية ورسالة معتمدة ومنشورة.', 'en' => 'An approved and published vision and mission document.']],
            ['type' => 'attachment', 'id' => $attachment->id, 'name' => ['ar' => 'لائحة الحوكمة المحدثة', 'en' => 'Updated governance charter']],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.label.en', 'Approved Institutional Excellence Model')
        ->assertJsonPath('meta.change_count', 5)
        ->assertJsonPath('meta.effect.kind', 'text_correction')
        ->assertJsonPath('meta.effect.scope', 'all')
        ->assertJsonPath('meta.effect.reaches_closed_assessments', true)
        ->assertJsonPath('meta.effect.rebinds_open_assessments', false)
        ->assertJsonPath('meta.effect.keeps_scores_of_unchanged_items', true);

    expect($main->refresh()->name->en)->toBe('Institutional Leadership')
        ->and($main->weight->toDecimal())->toBe('1000.00')
        ->and($sub->refresh()->description)->toBeNull()
        ->and($attachment->refresh()->name->ar)->toBe('لائحة الحوكمة المحدثة');

    $edit = CriteriaEditEvent::query()->findOrFail($response->json('meta.edit_event_id'));
    expect($edit->kind)->toBe(CriteriaEditKind::TextCorrection)
        ->and($edit->scope)->toBe(CriteriaEditScope::All)
        ->and($edit->to_version_id)->toBeNull()
        ->and(collect($edit->changes)->firstWhere('code', 'M1'))->toMatchArray([
            'change' => 'text_corrected',
            'fields' => ['name'],
            'before' => ['name' => ['ar' => 'القيادة الإدارية', 'en' => 'Administrative Leadership']],
            'score_affecting' => false,
        ])
        ->and(Audit::query()->where('event', AuditEvent::CriteriaVersionLabelsCorrected->value)->exists())->toBeTrue()
        ->and(Cache::has(PublishedCriteriaTree::CACHE_KEY))->toBeFalse();
    Notification::assertSentTo($administrator, CriteriaNotification::class, static fn (CriteriaNotification $notification): bool => $notification->notice->code === NotificationCode::CriteriaEditedWithScope);
});

it('corrects texts of an archived version bound to closed results', function (): void {
    Event::fake([CriteriaEditApplied::class]);
    $archived = archiveVersion(publishVersion(balancedDraft()));
    $main = mainOf($archived, 'M1');
    signIn(criteriaOfficer());

    $this->patchJson('/api/v1/criteria/versions/'.$archived->id.'/labels', [
        'nodes' => [['type' => 'main', 'id' => $main->id, 'description' => ['ar' => 'وصف مصحح للمعيار.', 'en' => 'A corrected description of the criterion.']]],
    ])->assertOk()->assertJsonPath('data.status', 'archived')->assertJsonPath('meta.change_count', 1);

    Event::assertDispatched(CriteriaEditApplied::class, static fn (CriteriaEditApplied $event): bool => $event->effect->reachesClosedAssessments() && $event->rescoredCodes() === []);
});

it('sends drafts to the regular editor', function (): void {
    $draft = balancedDraft();
    signIn(criteriaOfficer());

    $this->patchJson('/api/v1/criteria/versions/'.$draft->id.'/labels', [
        'nodes' => [['type' => 'main', 'id' => mainOf($draft, 'M1')->id, 'name' => ['ar' => 'القيادة', 'en' => 'Leadership']]],
    ])->assertConflict()->assertJsonPath('code', 'criteria.version_is_draft');
});

it('accepts only text fields that exist on each item type', function (array $node, string $field): void {
    $published = publishVersion(balancedDraft());
    $ids = ['main' => mainOf($published, 'M1')->id, 'sub' => subOf($published, 'M1.1')->id, 'point' => pointOf($published, 'M1.1.1')->id];
    signIn(criteriaOfficer());

    $this->patchJson('/api/v1/criteria/versions/'.$published->id.'/labels', ['nodes' => [[...$node, 'id' => $ids[$node['type']] ?? 1]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'a weight' => [['type' => 'main', 'weight' => '900'], 'nodes.0'],
    'an evaluation type' => [['type' => 'sub', 'evaluation_type' => 'direct'], 'nodes.0'],
    'a guiding point name' => [['type' => 'point', 'name' => ['ar' => 'نقطة', 'en' => 'Point']], 'nodes.0.name'],
    'an empty guiding point description' => [['type' => 'point', 'description' => null], 'nodes.0.description'],
    'an assessor note on a main criterion' => [['type' => 'main', 'assessor_note_label' => ['ar' => 'ملاحظة', 'en' => 'Note']], 'nodes.0.assessor_note_label'],
    'the version type' => [['type' => 'version', 'name' => ['ar' => 'إصدار', 'en' => 'Version']], 'nodes.0.type'],
    'a one-language name' => [['type' => 'main', 'name' => ['ar' => 'القيادة']], 'nodes.0.name.en'],
]);

it('refuses items that belong to another version', function (): void {
    $published = publishVersion(balancedDraft());
    $foreign = mainIn(draftVersion(), 'M9', '100.00');
    signIn(criteriaOfficer());

    $this->patchJson('/api/v1/criteria/versions/'.$published->id.'/labels', [
        'nodes' => [['type' => 'main', 'id' => $foreign->id, 'name' => ['ar' => 'القيادة', 'en' => 'Leadership']]],
    ])->assertUnprocessable()->assertJsonPath('code', 'criteria.node_not_in_version')->assertJsonValidationErrors('nodes.0.id');

    expect($foreign->refresh()->name->en)->toBe('Administrative Leadership');
});

it('records nothing when the texts are unchanged', function (): void {
    $published = publishVersion(balancedDraft());
    $main = mainOf($published, 'M1');
    signIn(criteriaOfficer());

    $this->patchJson('/api/v1/criteria/versions/'.$published->id.'/labels', [
        'nodes' => [['type' => 'main', 'id' => $main->id, 'name' => $main->name->toArray()]],
    ])->assertOk()->assertJsonPath('meta.change_count', 0)->assertJsonPath('meta.edit_event_id', null);

    expect(CriteriaEditEvent::query()->count())->toBe(0);
});

it('requires a reason when the Super Admin corrects published texts', function (): void {
    $published = publishVersion(balancedDraft());
    signIn(superAdmin());
    $payload = ['nodes' => [['type' => 'main', 'id' => mainOf($published, 'M1')->id, 'name' => ['ar' => 'القيادة', 'en' => 'Leadership']]]];

    $this->patchJson('/api/v1/criteria/versions/'.$published->id.'/labels', $payload)->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    $this->patchJson('/api/v1/criteria/versions/'.$published->id.'/labels', [...$payload, 'reason' => 'تصحيح خطأ إملائي في اسم المعيار.'])->assertOk();

    expect(CriteriaEditEvent::query()->firstOrFail()->reason)->toBe('تصحيح خطأ إملائي في اسم المعيار.');
});

it('denies text corrections to roles without criteria.manage', function (RoleKey $role): void {
    $published = publishVersion(balancedDraft());
    signIn(staffUser($role));

    $this->patchJson('/api/v1/criteria/versions/'.$published->id.'/labels', [
        'nodes' => [['type' => 'main', 'id' => mainOf($published, 'M1')->id, 'name' => ['ar' => 'القيادة', 'en' => 'Leadership']]],
    ])->assertForbidden();
})->with([
    'system administrator' => [RoleKey::SystemAdmin],
    'content officer' => [RoleKey::ContentOfficer],
]);
