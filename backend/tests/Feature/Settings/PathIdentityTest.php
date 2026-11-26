<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityRequestPath;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Models\Audit\Audit;
use App\Models\Settings\Setting;
use App\Notifications\Identity\SecurityAlertNotification;
use App\Services\Documents\SampleDocuments;
use App\Services\Paths\PathIdentityReader;
use App\Support\Assessments\PathPresenter;
use Illuminate\Support\Facades\Notification;

const PATH_IDENTITY_SETTINGS = '/api/v1/settings/assessment/paths/';

const PUBLIC_PATHS = '/api/v1/content/assessment-paths';

const NEW_ACCREDITED_DESCRIPTION = ['ar' => 'وصف جديد لمسار التقييم المعتمد', 'en' => 'A new description of the accredited path'];

const ACCREDITED_LABEL = ['ar' => 'التقييم المعتمد', 'en' => 'Accredited assessment'];

beforeEach(function (): void {
    Notification::fake();
});

it('serves the fixed label and the default description of every path', function (string $path, string $labelEn, string $descriptionAr): void {
    switchTo(systemAdmin())->getJson(PATH_IDENTITY_SETTINGS.$path)->assertOk()
        ->assertJsonPath('data.path', $path)
        ->assertJsonPath('data.path_label.en', $labelEn)
        ->assertJsonMissingPath('data.name')
        ->assertJsonPath('data.description.ar', $descriptionAr);
})->with([
    'self' => ['self', 'Self assessment', 'تقييم اختياري تجريه جهتك بنفسها لقياس جاهزيتها، ولا يمنح مستوى.'],
    'accredited' => ['accredited', 'Accredited assessment', 'تقييم يجريه مقيّم معتمد ويمنح مستوى التميّز وشهادته. تُحدَّد فترة الطلب تلقائياً من تاريخ إرساله.'],
    'advanced' => ['advanced', 'Advanced assessment (Award)', 'جائزة سنوية للجهات المتميزة، تُعلن نتائجها بعد اكتمال التقييم.'],
]);

it('saves the description of each path independently and audits the change', function (string $path): void {
    $description = ['ar' => 'وصف المسار الجديد للجهات', 'en' => 'The new description of the path'];

    switchTo(systemAdmin())->putJson(PATH_IDENTITY_SETTINGS.$path, ['description' => $description])->assertOk()
        ->assertJsonPath('data.path_label.ar', __('assessments.paths.'.$path, [], 'ar'))
        ->assertJsonPath('data.description', $description);

    test()->getJson(PATH_IDENTITY_SETTINGS.$path)->assertOk()->assertJsonPath('data.description', $description);

    foreach (AssessmentPath::cases() as $other) {
        if ($other->value !== $path) {
            test()->getJson(PATH_IDENTITY_SETTINGS.$other->value)->assertOk()->assertJsonPath('data.description.ar', __('assessments.path_descriptions.'.$other->value, [], 'ar'));
        }
    }

    expect(Audit::query()->where('event', AuditEvent::AssessmentSettingsUpdated->value)->count())->toBe(1)
        ->and(Setting::query()->where('key', 'paths.'.$path.'.description')->sole()->value)->toBe($description);

    test()->putJson(PATH_IDENTITY_SETTINGS.$path, ['description' => $description])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::AssessmentSettingsUpdated->value)->count())->toBe(1);
})->with(['self', 'accredited', 'advanced']);

it('refuses a path name on every path because path names are fixed', function (string $path): void {
    switchTo(systemAdmin())->putJson(PATH_IDENTITY_SETTINGS.$path, ['name' => ['ar' => 'اسم', 'en' => 'Name'], 'description' => NEW_ACCREDITED_DESCRIPTION], ['Accept-Language' => 'en'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('errors.name.0', 'The path name is fixed and cannot be edited.');

    expect(Setting::query()->where('key', 'like', 'paths.%')->exists())->toBeFalse();
})->with(['self', 'accredited', 'advanced']);

it('keeps the reviewed path settings alongside the description', function (): void {
    switchTo(systemAdmin())->putJson(PATH_IDENTITY_SETTINGS.'accredited', ['description' => NEW_ACCREDITED_DESCRIPTION, 'review_sla_days' => 10])->assertOk()
        ->assertJsonPath('data.path_label', ACCREDITED_LABEL)
        ->assertJsonPath('data.description', NEW_ACCREDITED_DESCRIPTION)
        ->assertJsonPath('data.review_sla_days', 10)
        ->assertJsonMissingPath('data.name')
        ->assertJsonStructure(['data' => ['upload_limits', 'entity_texts', 'is_paid', 'price']]);
});

it('returns only the label and description for the self assessment path', function (): void {
    $response = switchTo(systemAdmin())->getJson(PATH_IDENTITY_SETTINGS.'self')->assertOk();

    expect(array_keys($response->json('data')))->toBe(['path', 'path_label', 'description']);
});

it('refuses settings that do not apply to the self assessment path', function (array $payload, string $field): void {
    switchTo(systemAdmin())->putJson(PATH_IDENTITY_SETTINGS.'self', [...$payload, 'description' => ['ar' => 'وصف', 'en' => 'Description']], ['Accept-Language' => 'en'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('errors.'.$field.'.0', 'This setting does not apply to the self assessment path.');

    expect(Setting::query()->where('key', SettingKey::SelfPathDescription->value)->exists())->toBeFalse();
})->with([
    'review sla' => [['review_sla_days' => 5], 'review_sla_days'],
    'upload size' => [['upload_max_file_mb' => 5], 'upload_max_file_mb'],
    'evidence types' => [['evidence_allowed_types' => ['pdf']], 'evidence_allowed_types'],
    'files per sub-criterion' => [['evidence_max_files_per_sub' => 3], 'evidence_max_files_per_sub'],
    'entity texts' => [['entity_texts' => ['intro' => ['ar' => 'نص', 'en' => 'Text']]], 'entity_texts'],
]);

it('refuses an invalid description', function (string $path, array $payload, string $field): void {
    switchTo(systemAdmin())->putJson(PATH_IDENTITY_SETTINGS.$path, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'description without english' => ['self', ['description' => ['ar' => 'وصف']], 'description.en'],
    'description without arabic' => ['accredited', ['description' => ['en' => 'Description']], 'description.ar'],
    'blank arabic description' => ['advanced', ['description' => ['ar' => ' ', 'en' => 'Text']], 'description.ar'],
    'description too long' => ['self', ['description' => ['ar' => 'وصف', 'en' => str_repeat('d', 501)]], 'description.en'],
    'unknown language' => ['advanced', ['description' => ['ar' => 'وصف', 'en' => 'Description', 'fr' => 'Description']], 'description'],
    'null description' => ['self', ['description' => null], 'description'],
]);

it('requires a reason from the Super Admin to change a path description', function (): void {
    $admin = superAdmin();

    switchTo($admin)->putJson(PATH_IDENTITY_SETTINGS.'self', ['description' => ['ar' => 'وصف', 'en' => 'Description']])->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    test()->putJson(PATH_IDENTITY_SETTINGS.'self', ['description' => ['ar' => 'وصف', 'en' => 'Description'], 'reason' => 'توحيد أوصاف المسارات في المنصة'])->assertOk();

    Notification::assertSentTo($admin, SecurityAlertNotification::class);
});

it('refuses the self path settings without the assessment settings permission', function (RoleKey $role): void {
    switchTo(staffUser($role))->getJson(PATH_IDENTITY_SETTINGS.'self')->assertForbidden();
    test()->putJson(PATH_IDENTITY_SETTINGS.'self', ['description' => ['ar' => 'وصف', 'en' => 'Description']])->assertForbidden();
})->with([
    'requests follower' => [RoleKey::RequestFollower],
    'content officer' => [RoleKey::ContentOfficer],
]);

it('refuses the path settings to guests and entities', function (): void {
    test()->getJson(PATH_IDENTITY_SETTINGS.'self')->assertUnauthorized();
    test()->putJson(PATH_IDENTITY_SETTINGS.'self', ['description' => ['ar' => 'وصف', 'en' => 'Description']])->assertUnauthorized();
    switchTo(ownerOf(entityWithOwner()))->putJson(PATH_IDENTITY_SETTINGS.'accredited', ['description' => NEW_ACCREDITED_DESCRIPTION])->assertForbidden();

    expect(Setting::query()->where('key', SettingKey::AccreditedPathDescription->value)->exists())->toBeFalse();
});

it('publishes the three paths to guests in order with fixed names and refreshes the descriptions after a change', function (): void {
    test()->getJson(PUBLIC_PATHS)->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0', [
            'path' => 'self',
            'name' => ['ar' => 'التقييم الذاتي', 'en' => 'Self assessment'],
            'description' => ['ar' => 'تقييم اختياري تجريه جهتك بنفسها لقياس جاهزيتها، ولا يمنح مستوى.', 'en' => 'An optional assessment your entity runs itself to gauge its readiness; it grants no level.'],
        ])
        ->assertJsonPath('data.1.path', 'accredited')
        ->assertJsonPath('data.1.name.en', 'Accredited assessment')
        ->assertJsonPath('data.2.path', 'advanced')
        ->assertJsonPath('data.2.description.en', 'An annual award for excellent entities; results are announced once the assessment is complete.');

    switchTo(systemAdmin())->putJson(PATH_IDENTITY_SETTINGS.'accredited', ['description' => NEW_ACCREDITED_DESCRIPTION])->assertOk();

    test()->getJson(PUBLIC_PATHS)->assertOk()
        ->assertJsonPath('data.1.name', ACCREDITED_LABEL)
        ->assertJsonPath('data.1.description', NEW_ACCREDITED_DESCRIPTION);
});

it('labels the path everywhere with the fixed translation', function (): void {
    $owner = ownerOf(entityWithOwner());
    inAppNotification($owner, NotificationCode::SelfAssessmentSubmitted, ['path' => AssessmentPath::Accredited]);
    $reader = app(PathIdentityReader::class);
    $samples = app(SampleDocuments::class);

    switchTo(systemAdmin())->putJson(PATH_IDENTITY_SETTINGS.'accredited', ['description' => NEW_ACCREDITED_DESCRIPTION])->assertOk();

    expect(PathPresenter::of(AssessmentPath::Accredited)['path_label'])->toBe(ACCREDITED_LABEL)
        ->and(PathPresenter::ofRequestPath(EntityRequestPath::Accredited)['path_label'])->toBe(ACCREDITED_LABEL)
        ->and($reader->labelOf(EntityRequestPath::Bag)->en)->toBe('Quick Excellence Kit')
        ->and($reader->purposeLabelOf(PaymentPurpose::Accredited)->toArray())->toBe(ACCREDITED_LABEL)
        ->and($reader->purposeLabelOf(PaymentPurpose::Award)->en)->toBe(__('finance.purposes.award', [], 'en'))
        ->and($samples->all()['sample-feedback-report-en.pdf']->data['path_label'])->toBe(ACCREDITED_LABEL)
        ->and($samples->all()['sample-receipt.pdf']->data['purpose_label'])->toBe(ACCREDITED_LABEL)
        ->and($reader->nameOf(AssessmentPath::SelfAssessment)->en)->toBe('Self assessment');

    switchTo($owner)->getJson('/api/v1/notifications')->assertOk()
        ->assertJsonPath('data.0.path', 'accredited')
        ->assertJsonPath('data.0.path_label', ACCREDITED_LABEL);
});

it('ignores a path name stored before names became fixed', function (): void {
    Setting::query()->forceCreate(['group' => 'paths', 'key' => 'paths.accredited.name', 'type' => 'json', 'value' => ['ar' => 'اسم قديم', 'en' => 'Old name']]);

    expect(app(PathIdentityReader::class)->nameOf(AssessmentPath::Accredited)->toArray())->toBe(ACCREDITED_LABEL);
    test()->getJson(PUBLIC_PATHS)->assertOk()->assertJsonPath('data.1.name', ACCREDITED_LABEL);
});
