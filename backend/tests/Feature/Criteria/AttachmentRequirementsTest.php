<?php

declare(strict_types=1);

use App\Models\Criteria\AttachmentRequirement;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\MainCriterion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @return array<string, mixed>
 */
function planAttachment(array $overrides = []): array
{
    return [
        'name' => ['ar' => 'الخطة الاستراتيجية المعتمدة', 'en' => 'Approved strategic plan'],
        'guidance' => ['ar' => 'ارفع الخطة موقّعة من صاحب الصلاحية.', 'en' => 'Upload the plan signed by the approving authority.'],
        'required' => true,
        'allowed_types' => ['pdf', 'docx'],
        'max_files' => 2,
        ...$overrides,
    ];
}

it('gives a main criterion its own required attachments with guidance, file types and a file limit', function (): void {
    $draft = balancedDraft();
    signIn(criteriaOfficer());

    $created = $this->postJson('/api/v1/criteria/mains', [
        'name' => ['ar' => 'الابتكار وإدارة المعرفة', 'en' => 'Innovation and Knowledge Management'],
        'weight' => '600',
        'attachments' => [planAttachment()],
    ])->assertCreated()
        ->assertJsonPath('data.attachments.0.name.en', 'Approved strategic plan')
        ->assertJsonPath('data.attachments.0.guidance.ar', 'ارفع الخطة موقّعة من صاحب الصلاحية.')
        ->assertJsonPath('data.attachments.0.allowed_types', ['pdf', 'docx'])
        ->assertJsonPath('data.attachments.0.max_files', 2)
        ->assertJsonPath('data.attachments.0.required', true);

    $this->putJson('/api/v1/criteria/mains/'.$created->json('data.id'), ['attachments' => [planAttachment(['allowed_types' => null, 'max_files' => null, 'guidance' => null])]])
        ->assertOk()
        ->assertJsonPath('data.attachments.0.allowed_types', null)
        ->assertJsonPath('data.attachments.0.max_files', null)
        ->assertJsonPath('data.attachments.0.guidance', null);

    $this->getJson("/api/v1/criteria/versions/{$draft->id}/tree")->assertOk()
        ->assertJsonPath('data.mains.1.attachments.0.key', AttachmentRequirement::query()->where('main_criterion_id', $created->json('data.id'))->value('key'));
});

it('keeps the new fields on sub-criteria and indicators too', function (): void {
    $draft = balancedDraft();
    signIn(criteriaOfficer());

    $this->putJson('/api/v1/criteria/subs/'.subOf($draft, 'M1.1')->id, ['attachments' => [planAttachment()]])->assertOk()
        ->assertJsonPath('data.attachments.0.max_files', 2);
    $this->putJson('/api/v1/criteria/points/'.pointOf($draft, 'M1.1.1')->id, ['attachments' => [planAttachment(['required' => false])]])->assertOk()
        ->assertJsonPath('data.point.attachments.0.allowed_types', ['pdf', 'docx'])
        ->assertJsonPath('data.point.attachments.0.required', false);
});

it('validates the new attachment fields', function (array $attachment, string $field): void {
    $draft = balancedDraft();
    signIn(criteriaOfficer());

    $this->putJson('/api/v1/criteria/subs/'.subOf($draft, 'M1.1')->id, ['attachments' => [$attachment]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown file type' => [planAttachment(['allowed_types' => ['exe']]), 'attachments.0.allowed_types.0'],
    'repeated file type' => [planAttachment(['allowed_types' => ['pdf', 'pdf']]), 'attachments.0.allowed_types.0'],
    'no file type in a list' => [planAttachment(['allowed_types' => []]), 'attachments.0.allowed_types'],
    'zero files' => [planAttachment(['max_files' => 0]), 'attachments.0.max_files'],
    'too many files' => [planAttachment(['max_files' => 51]), 'attachments.0.max_files'],
    'guidance without English' => [planAttachment(['guidance' => ['ar' => 'ارفع الخطة الموقّعة.']]), 'attachments.0.guidance.en'],
    'unknown field' => [planAttachment(['template' => 'x']), 'attachments.0'],
]);

it('copies main-criterion attachments into a new draft and freezes them once published', function (): void {
    $draft = balancedDraft();
    signIn(criteriaOfficer());
    $this->putJson('/api/v1/criteria/mains/'.mainOf($draft, 'M1')->id, ['attachments' => [planAttachment()]])->assertOk();
    $published = publishVersion($draft);

    $nextId = $this->postJson('/api/v1/criteria/versions', [])->assertCreated()->json('data.id');
    $copy = AttachmentRequirement::query()->where('main_criterion_id', mainOf(CriteriaVersion::query()->findOrFail($nextId), 'M1')->id)->sole();

    expect($copy->max_files)->toBe(2)
        ->and($copy->allowed_types)->toBe(['pdf', 'docx'])
        ->and($copy->guidance?->en)->toBe('Upload the plan signed by the approving authority.')
        ->and(fn () => mainOf($published, 'M1')->attachmentRequirements()->create(['key' => 'late', 'name' => ['ar' => 'مرفق متأخر', 'en' => 'Late file'], 'is_required' => true, 'sort_order' => 2]))
        ->toThrow(QueryException::class, 'criteria_structure');
});

it('corrects the guidance of a published attachment in place', function (): void {
    $draft = balancedDraft();
    signIn(criteriaOfficer());
    $this->putJson('/api/v1/criteria/mains/'.mainOf($draft, 'M1')->id, ['attachments' => [planAttachment()]])->assertOk();
    $published = publishVersion($draft);
    $attachment = AttachmentRequirement::query()->where('main_criterion_id', mainOf($published, 'M1')->id)->sole();

    $this->patchJson("/api/v1/criteria/versions/{$published->id}/labels", [
        'nodes' => [['type' => 'attachment', 'id' => $attachment->id, 'guidance' => ['ar' => 'ارفع الخطة المعتمدة بصيغة PDF.', 'en' => 'Upload the approved plan as a PDF.']]],
    ])->assertOk();

    expect($attachment->refresh()->guidance?->en)->toBe('Upload the approved plan as a PDF.')
        ->and(MainCriterion::query()->findOrFail(mainOf($published, 'M1')->id)->attachmentRequirements()->count())->toBe(1);
});
