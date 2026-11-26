<?php

declare(strict_types=1);

use App\Data\Entities\EntityNotice;
use App\Data\Mail\CertificateSeal;
use App\Data\Mail\MailSample;
use App\Data\Mail\RenderedMail;
use App\Data\Notifications\NoticeText;
use App\Enums\Certificates\CertificateLevel;
use App\Enums\Identity\Locale;
use App\Enums\Notifications\AccreditedJourneyStage;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use App\Services\Notifications\MailPreviewRenderer;
use App\Services\Notifications\MailSamples;
use App\Support\Mail\SampleReplacements;
use Illuminate\Support\Facades\Notification;

const CERTIFICATE_TEXT = 'notifications.assessments.accredited.ACC-09.entity';

beforeEach(function (): void {
    Notification::fake();
});

function sampleMail(string $name, Locale $locale): RenderedMail
{
    $code = NotificationCode::from(explode('.', $name, 2)[0]);
    $samples = array_values(array_filter(app(MailSamples::class)->forCode($code, $locale), static fn (MailSample $sample): bool => $sample->name === $name));

    return app(MailPreviewRenderer::class)->render($samples[0], $locale);
}

function sentNotice(User $recipient, NotificationCode $code): EntityNotice
{
    $sent = Notification::sent($recipient, EntityNotification::class)
        ->first(static fn (EntityNotification $notification): bool => $notification->notice->code === $code);
    assert($sent instanceof EntityNotification);

    return $sent->notice;
}

function sealMail(CertificateLevel $level): RenderedMail
{
    $notice = EntityNotice::fromText(NotificationCode::AccreditedCertificateIssued, new NoticeText(CERTIFICATE_TEXT, SampleReplacements::for(CERTIFICATE_TEXT)), '/certificates')
        ->mailOnly()
        ->withSeal(new CertificateSeal($level));
    $recipient = new User()->forceFill(['id' => 9001, 'name' => 'Sara', 'email' => 'sara@example.sa']);

    return app(MailPreviewRenderer::class)->render(new MailSample('seal', new EntityNotification($notice), $recipient), Locale::Arabic);
}

it('shows the steps of a submitted request, the first done and the assignment current', function (Locale $locale): void {
    $mail = sampleMail('ACC-03.assessments.accredited.entity', $locale);
    $lang = $locale->value;
    preg_match_all('#<span class="mail-step-dot mail-step-dot-(\w+)"[^>]*>([^<]+)</span>#', $mail->html, $dots);

    expect($mail->html)->toContain(e(__('mail.progress.heading', [], $lang)))
        ->and($dots[1])->toBe(['done', 'current', 'next', 'next'])
        ->and($dots[2])->toBe(['✓', '2', '3', '4'])
        ->and($mail->html)->toContain(e(__('mail.progress.stages.assignment', [], $lang)).' <span class="mail-step-state mail-step-state-current"')
        ->and($mail->html)->toContain('· '.e(__('mail.progress.states.current', [], $lang)).'</span>')
        ->and($mail->text)->toContain('✓ '.__('mail.progress.stages.submitted', [], $lang).' ('.__('mail.progress.states.done', [], $lang).')')
        ->and($mail->text)->toContain('2. '.__('mail.progress.stages.assignment', [], $lang).' ('.__('mail.progress.states.current', [], $lang).')')
        ->and($mail->text)->toContain('4. '.__('mail.progress.stages.result', [], $lang)."\n");
})->with(Locale::cases());

it('shows the level seal of an issued certificate as a second inline image', function (Locale $locale): void {
    $mail = sampleMail('ACC-09.assessments.accredited.entity', $locale);
    $level = __('certificates.levels.gold', [], $locale->value);

    expect($mail->inlineImages)->toBe(2)
        ->and($mail->html)->toMatch('#<table class="mail-seal mail-seal-gold"#')
        ->and($mail->html)->toMatch('#<img class="mail-seal-image" src="cid:[^"]+"[^>]*alt=""#')
        ->and($mail->html)->toMatch('#<p class="mail-seal-level"[^>]*color: \#664f05[^>]*>'.preg_quote(e($level), '#').'</p>#')
        ->and($mail->text)->toContain($level.' — '.__('mail.seal.caption', [], $locale->value));
})->with(Locale::cases());

it('gives each certificate level its own seal and colours', function (CertificateLevel $level, string $ink): void {
    $mail = sealMail($level);

    expect($mail->inlineImages)->toBe(2)
        ->and($mail->html)->toContain('<table class="mail-seal mail-seal-'.$level->value.'"')
        ->and($mail->html)->toMatch('#<p class="mail-seal-level"[^>]*color: '.$ink.'#')
        ->and(file_exists(resource_path('mail/seals/seal-'.$level->value.'.png')))->toBeTrue();
})->with([
    'bronze' => [CertificateLevel::Bronze, '\#6b4520'],
    'silver' => [CertificateLevel::Silver, '\#454d4d'],
    'gold' => [CertificateLevel::Gold, '\#664f05'],
    'diamond' => [CertificateLevel::Diamond, '\#145e5e'],
]);

it('keeps the staff copies of ACC-03 and ACC-09 without steps or seal', function (string $name): void {
    $mail = sampleMail($name, Locale::Arabic);

    expect($mail->html)->not->toContain('class="mail-progress"')
        ->and($mail->html)->not->toContain('class="mail-seal')
        ->and($mail->inlineImages)->toBe(1);
})->with(['ACC-03.assessments.accredited.staff', 'ACC-09.assessments.accredited.staff']);

it('sends the entity the steps of its submitted request and the staff a copy without them', function (): void {
    $follower = requestFollower();
    $assessment = submittedAccreditedRequest(publishedAccreditedCriteria());
    $entityNotice = sentNotice(ownerOf($assessment->entity), NotificationCode::AccreditedSubmitted);
    $staffNotice = sentNotice($follower, NotificationCode::AccreditedSubmitted);

    expect($entityNotice->blocks->progress?->current)->toBe(AccreditedJourneyStage::Assignment)
        ->and($staffNotice->blocks->progress)->toBeNull();
});

it('sends the entity the seal of its certificate level and the admin a copy without it', function (): void {
    $admin = systemAdmin();
    ['assessment' => $assessment, 'certificate' => $certificate] = certifiedAccreditedRequest();

    expect(sentNotice(ownerOf($assessment->entity), NotificationCode::AccreditedCertificateIssued)->blocks->seal?->level)->toBe($certificate->level)
        ->and(sentNotice($admin, NotificationCode::AccreditedCertificateIssued)->blocks->seal)->toBeNull();
});
