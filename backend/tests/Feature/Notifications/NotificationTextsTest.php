<?php

declare(strict_types=1);

use App\Data\Entities\EntityNotice;
use App\Data\Mail\MailSample;
use App\Enums\Identity\Locale;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Notifications\NotificationTextOverride;
use App\Notifications\Entities\EntityNotification;
use App\Services\Notifications\MailPreviewRenderer;
use App\Services\Notifications\MailSamples;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

const NOTIFICATION_TEXTS = '/api/v1/settings/notifications';

/**
 * @return array<string, array{ar: string, en: string}>
 */
function registeredTexts(string $title = 'سجّلت :entity في المنصة', string $body = 'نرحّب بانضمام :entity، وسنراجع طلبها قريبًا.'): array
{
    return [
        'title' => ['ar' => $title, 'en' => ':entity joined the platform'],
        'body' => ['ar' => $body, 'en' => 'Welcome :entity; we will review the registration soon.'],
    ];
}

function saveRegisteredText(array $parts): TestResponse
{
    return switchTo(systemAdmin())->putJson(NOTIFICATION_TEXTS.'/texts/entities.REG-01.entity', ['parts' => $parts]);
}

it('lists the texts of a type with their variant, variables and shipped defaults', function (): void {
    $response = switchTo(systemAdmin())->getJson(NOTIFICATION_TEXTS.'/types/REG-01')->assertOk()
        ->assertJsonPath('data.code', 'REG-01')
        ->assertJsonPath('data.is_mandatory', false);

    $texts = collect($response->json('data.texts'))->keyBy('key');
    expect($texts->keys()->all())->toContain('entities.REG-01.entity', 'entities.REG-01.staff')
        ->and($texts['entities.REG-01.entity']['variant'])->toBe('entity')
        ->and($texts['entities.REG-01.entity']['is_customized'])->toBeFalse()
        ->and($texts['entities.REG-01.entity']['variables'])->toContain('entity')
        ->and($texts['entities.REG-01.entity']['required_variables'])->toBe([])
        ->and($texts['entities.REG-01.entity']['parts']['title']['default']['ar'])->toBe(__('notifications.entities.REG-01.entity.title', [], 'ar'));
});

it('marks every shipped variable of a core type as required and its button as fixed', function (): void {
    $response = switchTo(systemAdmin())->getJson(NOTIFICATION_TEXTS.'/types/USR-01')->assertOk()->assertJsonPath('data.is_core', true);
    $invitation = collect($response->json('data.texts'))->firstWhere('key', 'staff_invited');

    expect($invitation['variant'])->toBe('staff_invited')
        ->and($invitation['required_variables'])->toBe(['duration'])
        ->and(array_keys($invitation['parts']))->toBe(['subject', 'line', 'action', 'expiry'])
        ->and($invitation['parts']['action']['is_fixed'])->toBeTrue()
        ->and($invitation['parts']['subject']['is_fixed'])->toBeFalse()
        ->and(collect($invitation['parts'])->pluck('channel')->unique()->all())->toBe(['email']);
});

it('gives each text its audience and each part its channel, with the e-mail starting from the in-app wording', function (): void {
    $texts = collect(switchTo(systemAdmin())->getJson(NOTIFICATION_TEXTS.'/types/REG-01')->assertOk()->json('data.texts'))->keyBy('key');
    $entity = $texts['entities.REG-01.entity'];

    expect($entity['audience'])->toBe('beneficiary')
        ->and($texts['entities.REG-01.staff']['audience'])->toBe('management')
        ->and($entity['parts']['title']['channel'])->toBe('in_app')
        ->and($entity['parts']['email_subject']['channel'])->toBe('email')
        ->and($entity['parts']['email_subject']['default'])->toBe($entity['parts']['title']['default'])
        ->and($entity['parts']['email_body']['default'])->toBe($entity['parts']['body']['default']);
});

it('saves a text, audits it and sends it from then on in both languages', function (): void {
    saveRegisteredText(registeredTexts())->assertOk()
        ->assertJsonPath('data.texts.0.is_customized', true);

    expect(NotificationTextOverride::query()->sole()->parts['title']['ar'])->toBe('سجّلت :entity في المنصة')
        ->and(Audit::query()->where('auditable_type', (new NotificationTextOverride)->getMorphClass())->exists())->toBeTrue();

    $title = LocalizedText::fromTranslationWith('notifications.entities.REG-01.entity.title', ['entity' => 'جمعية الأمل الخيرية']);
    expect($title->ar)->toBe('سجّلت جمعية الأمل الخيرية في المنصة')
        ->and($title->en)->toBe('جمعية الأمل الخيرية joined the platform');
});

it('puts an edited subject in the branded e-mail of a core notification', function (): void {
    $invitation = collect(switchTo(systemAdmin())->getJson(NOTIFICATION_TEXTS.'/types/USR-01')->json('data.texts'))->firstWhere('key', 'staff_invited');
    $parts = array_map(static fn (array $part): array => $part['current'], array_filter($invitation['parts'], static fn (array $part): bool => ! $part['is_fixed']));
    $parts['subject'] = ['ar' => 'دعوتك إلى فريق منصة مؤشر', 'en' => 'Your invitation to the Moashir team'];

    switchTo(systemAdmin())->putJson(NOTIFICATION_TEXTS.'/texts/staff_invited', ['parts' => $parts])->assertOk();

    $sample = app(MailSamples::class)->forCode(NotificationCode::StaffInvited, Locale::Arabic)[0];
    expect(app(MailPreviewRenderer::class)->render($sample, Locale::Arabic)->subject)->toBe('منصة مؤشر — دعوتك إلى فريق منصة مؤشر');
});

it('refuses texts that break the template', function (array $parts, string $field): void {
    saveRegisteredText($parts)->assertUnprocessable()->assertJsonValidationErrors([$field]);
})->with([
    'unknown variable' => [registeredTexts('مرحبًا :secret'), 'parts.title.ar'],
    'part the template does not have' => [[...registeredTexts(), 'footer' => ['ar' => 'تذييل', 'en' => 'Footer']], 'parts'],
    'empty English' => [['title' => ['ar' => 'عنوان', 'en' => ' '], 'body' => registeredTexts()['body']], 'parts.title.en'],
    'too long' => [registeredTexts(str_repeat('ن', 2001)), 'parts.title.ar'],
    'html markup' => [registeredTexts('مرحبًا <a href="https://phish.example">:entity</a>'), 'parts.title.ar'],
]);

it('refuses to change the button or add a text message to a core notification', function (string $part): void {
    switchTo(systemAdmin())->putJson(NOTIFICATION_TEXTS.'/texts/staff_invited', ['parts' => [$part => ['ar' => 'اضغط هنا', 'en' => 'Click here']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parts.'.$part]);

    expect(NotificationTextOverride::query()->count())->toBe(0);
})->with(['action', 'sms']);

it('refuses a text message longer than three messages', function (): void {
    saveRegisteredText([...registeredTexts(), 'sms' => ['ar' => str_repeat('ن', 202), 'en' => 'Short enough']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parts.sms.ar'])
        ->assertJsonMissingValidationErrors(['parts.sms.en']);
});

it('sends the e-mail its own subject and Markdown-lite body, leaving the in-app text as it was', function (): void {
    $key = 'assessments.accredited.ACC-07.entity';
    $parts = [
        'email_subject' => ['ar' => 'بنود الطلب :number بانتظارك', 'en' => 'Request :number needs items'],
        'email_body' => [
            'ar' => "أكمل **البنود المطلوبة** قبل :deadline.\n- راجع [دليل الشواهد](https://moashir.ttshub.live/help)\n- [رابط آخر](http://phish.example)",
            'en' => 'Complete the **requested items** before :deadline.',
        ],
    ];

    switchTo(systemAdmin())->putJson(NOTIFICATION_TEXTS.'/texts/'.$key, ['parts' => $parts])->assertOk();
    $mail = app(MailPreviewRenderer::class)->render(app(MailSamples::class)->forCode(NotificationCode::AccreditedCompletionRequested, Locale::Arabic)[0], Locale::Arabic);

    expect($mail->subject)->toContain('بانتظارك')
        ->and($mail->html)->toContain('<strong>البنود المطلوبة</strong>')
        ->and($mail->html)->toMatch('#<a href="https://moashir\.ttshub\.live/help"[^>]*>دليل الشواهد</a>#u')
        ->and($mail->html)->not->toContain('href="http://phish.example"')
        ->and($mail->text)->toContain('دليل الشواهد (https://moashir.ttshub.live/help)')
        ->and($mail->text)->not->toContain('<strong>')
        ->and(__('notifications.'.$key.'.title', [], 'ar'))->not->toContain('بانتظارك');
});

it('puts a user-written value into the Markdown-lite e-mail as literal text only', function (string $entity, string $notInHtml, string $inText): void {
    $body = ['ar' => 'سجّلت :entity في المنصة. **راجعها الآن** عبر [لوحة الجهات](https://moashir.ttshub.live/admin/entities).', 'en' => ':entity registered.'];
    switchTo(systemAdmin())->putJson(NOTIFICATION_TEXTS.'/texts/entities.REG-01.staff', ['parts' => ['email_body' => $body]])->assertOk();
    $notice = EntityNotice::for(NotificationCode::EntityRegistered, EntityNotice::FOR_STAFF, ['entity' => $entity, 'reference' => 'ENT-000001'], 'admin/entities/1');

    $mail = app(MailPreviewRenderer::class)->render(new MailSample('injection', new EntityNotification($notice), systemAdmin()), Locale::Arabic);

    expect($mail->html)->not->toContain($notInHtml)
        ->and($mail->html)->toContain('<strong>راجعها الآن</strong>')
        ->and($mail->html)->toMatch('#<a href="https://moashir\.ttshub\.live/admin/entities"[^>]*>لوحة الجهات</a>#u')
        ->and($mail->text)->toContain($inText);
})->with([
    'link in a name' => ['[حدّث بياناتك](https://evil.example/login)', 'href="https://evil.example/login"', 'سجّلت [حدّث بياناتك](https://evil.example/login) في المنصة'],
    'bold in a name' => ['**عاجل**', '<strong>عاجل</strong>', 'سجّلت **عاجل** في المنصة'],
    'script in a name' => ['<script>alert(1)</script>', '<script>alert(1)</script>', 'سجّلت <script>alert(1)</script> في المنصة'],
]);

it('keeps the required variables of a core notification', function (): void {
    $parts = ['expiry' => ['ar' => 'الدعوة صالحة لمدة قصيرة.', 'en' => 'The invitation is valid for :duration.']];

    switchTo(systemAdmin())->putJson(NOTIFICATION_TEXTS.'/texts/staff_invited', ['parts' => $parts])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parts.expiry.ar'])
        ->assertJsonMissingValidationErrors(['parts.expiry.en']);
});

it('sends an edited summary line and button label in the notice e-mail and drops them on reset', function (): void {
    $key = 'assessments.accredited.ACC-07.entity';
    $text = collect(switchTo(systemAdmin())->getJson(NOTIFICATION_TEXTS.'/types/ACC-07')->assertOk()->json('data.texts'))->firstWhere('key', $key);
    $parts = array_map(static fn (array $part): array => $part['current'], $text['parts']);
    $parts['summary'] = ['ar' => 'بقيت بنود مفتوحة في الطلب :number، أعد إرساله قبل :deadline', 'en' => 'Items are still open in request :number; resubmit by :deadline'];
    $parts['action'] = ['ar' => 'أكمل البنود الآن', 'en' => 'Complete the items now'];
    $render = static fn (): string => app(MailPreviewRenderer::class)->render(
        app(MailSamples::class)->forCode(NotificationCode::AccreditedCompletionRequested, Locale::Arabic)[0],
        Locale::Arabic,
    )->html;

    expect(array_keys($text['parts']))->toContain('summary', 'action');

    switchTo(systemAdmin())->putJson(NOTIFICATION_TEXTS.'/texts/'.$key, ['parts' => $parts])->assertOk();

    expect($render())->toMatch('#<p class="mail-summary"[^>]*>بقيت بنود مفتوحة في الطلب [^<]+</p>#')
        ->and($render())->toMatch('#class="mail-button-link"[^>]*>أكمل البنود الآن</a>#');

    switchTo(systemAdmin())->deleteJson(NOTIFICATION_TEXTS.'/texts/'.$key)->assertNoContent();

    expect($render())->not->toContain('بقيت بنود مفتوحة')
        ->and($render())->toContain('استكمل البنود المطلوبة وأعد إرسال الطلب قبل')
        ->and($render())->toMatch('#class="mail-button-link"[^>]*>استكمال الطلب</a>#');
});

it('resets a text to the shipped wording', function (): void {
    saveRegisteredText(registeredTexts())->assertOk();

    switchTo(systemAdmin())->deleteJson(NOTIFICATION_TEXTS.'/texts/entities.REG-01.entity')->assertNoContent();

    expect(NotificationTextOverride::query()->count())->toBe(0)
        ->and(__('notifications.entities.REG-01.entity.title', ['entity' => 'X'], 'ar'))->not->toContain('سجّلت');
});

it('answers 404 for an unknown type or text key', function (string $method, string $path): void {
    switchTo(systemAdmin())->json($method, NOTIFICATION_TEXTS.$path, ['parts' => registeredTexts()])->assertNotFound();
})->with([
    'type' => ['GET', '/types/XYZ-99'],
    'text on save' => ['PUT', '/texts/entities.REG-99.entity'],
    'label fragment' => ['PUT', '/texts/files.purposes'],
    'text on reset' => ['DELETE', '/texts/entities.REG-99.entity'],
]);

it('denies the notification texts to roles without the settings permission', function (string $method, string $path): void {
    switchTo(staffUser(RoleKey::Assessor))->json($method, NOTIFICATION_TEXTS.$path, ['parts' => registeredTexts()])->assertForbidden();
})->with([
    ['GET', '/types/REG-01'],
    ['PUT', '/texts/entities.REG-01.entity'],
    ['DELETE', '/texts/entities.REG-01.entity'],
]);

it('lets a long-running queue worker pick up a changed text at its next job', function (): void {
    expect(__('notifications.entities.REG-01.entity.title', ['entity' => 'X'], 'en'))->not->toContain('joined');
    NotificationTextOverride::query()->create(['template_key' => 'entities.REG-01.entity', 'code' => NotificationCode::EntityRegistered, 'parts' => registeredTexts()]);
    cache()->forget('notifications.text-overrides');

    Event::dispatch(new JobProcessing('redis', Mockery::mock(Job::class)->shouldIgnoreMissing()));

    expect(__('notifications.entities.REG-01.entity.title', ['entity' => 'X'], 'en'))->toBe('X joined the platform');
});
