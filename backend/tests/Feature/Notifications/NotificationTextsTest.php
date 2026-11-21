<?php

declare(strict_types=1);

use App\Enums\Identity\Locale;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Notifications\NotificationTextOverride;
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

it('marks every shipped variable of a mandatory type as required', function (): void {
    $invitation = collect(switchTo(systemAdmin())->getJson(NOTIFICATION_TEXTS.'/types/USR-01')->assertOk()->json('data.texts'))->firstWhere('key', 'staff_invited');

    expect($invitation['variant'])->toBe('staff_invited')
        ->and($invitation['required_variables'])->toBe(['duration'])
        ->and(array_keys($invitation['parts']))->toBe(['subject', 'line', 'action', 'expiry']);
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

it('puts an edited subject in the branded e-mail of a mandatory notification', function (): void {
    $invitation = collect(switchTo(systemAdmin())->getJson(NOTIFICATION_TEXTS.'/types/USR-01')->json('data.texts'))->firstWhere('key', 'staff_invited');
    $parts = array_map(static fn (array $part): array => $part['current'], $invitation['parts']);
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

it('keeps the required variables of a mandatory notification', function (): void {
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
