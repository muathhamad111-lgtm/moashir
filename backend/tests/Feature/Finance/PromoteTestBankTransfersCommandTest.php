<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Certificates\CertificateStatus;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Platform\DemoRecordKind;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Certificates\Certificate;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Models\Platform\DemoRecord;
use App\Services\Certificates\VerificationCode;
use Illuminate\Support\Facades\Notification;

const PROMOTION_REASON = 'قرار المشغّل 2026-11-19: التحويلات المعتمدة رسمية.';

beforeEach(function (): void {
    Notification::fake();
});

/**
 * A request paid by an entity bank transfer that still opened in test mode (rule BT-3 before
 * 2026-11-19) and completed with a test certificate.
 */
function legacyTestTransferRequest(?User $assessor = null, bool $completed = true, ?CriteriaVersion $version = null): Assessment
{
    $assessor ??= assessorUser();
    $assessment = scoredAccreditedReview($version ?? publishedAccreditedCriteria(), $assessor);
    asTestTransfer($assessment->payment_id);

    if ($completed) {
        switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk();
        app('auth')->forgetGuards();
    }

    return $assessment->refresh();
}

/**
 * Turns a payment into the legacy state: an entity bank transfer recorded in test mode.
 */
function asTestTransfer(int $paymentId): void
{
    Payment::query()->whereKey($paymentId)->update([
        'channel' => PaymentChannel::BankTransfer->value,
        'mode' => IntegrationMode::Test->value,
        'provider' => null,
        'provider_intent_id' => null,
        'checkout_client_secret' => null,
        'provider_payment_id' => null,
        'bound_at' => null,
        'method' => null,
        'card_last4' => null,
        'expires_at' => null,
    ]);
}

it('lists the test-mode bank transfers on a dry run and changes nothing', function (): void {
    $assessment = legacyTestTransferRequest();
    $reference = Payment::query()->findOrFail($assessment->payment_id)->reference;

    $this->artisan('moashir:finance:promote-test-transfers', ['--dry-run' => true])
        ->expectsOutputToContain('1 bank transfers would be promoted.')
        ->expectsOutputToContain($reference)
        ->assertSuccessful();

    expect(Payment::query()->findOrFail($assessment->payment_id)->mode)->toBe(IntegrationMode::Test)
        ->and(Certificate::query()->sole()->is_test)->toBeTrue();
});

it('refuses to promote without a reason', function (): void {
    $assessment = legacyTestTransferRequest();

    $this->artisan('moashir:finance:promote-test-transfers', ['--reason' => 'قصير'])->assertExitCode(2);

    expect(Payment::query()->findOrFail($assessment->payment_id)->mode)->toBe(IntegrationMode::Test);
});

it('makes the transfer live and re-issues its test certificate as an official, verifiable replacement', function (): void {
    $assessment = legacyTestTransferRequest();
    $test = Certificate::query()->sole();

    $this->artisan('moashir:finance:promote-test-transfers', ['--reason' => PROMOTION_REASON])
        ->expectsOutputToContain('Promoted 1 bank transfers.')
        ->assertSuccessful();

    $official = Certificate::query()->where('assessment_id', $assessment->id)->where('status', CertificateStatus::Issued)->sole();
    $audit = Audit::query()->where('event', AuditEvent::BankTransferPromotedToLive->value)->sole();

    expect(Payment::query()->findOrFail($assessment->payment_id)->mode)->toBe(IntegrationMode::Live)
        ->and($test->refresh()->status)->toBe(CertificateStatus::Superseded)
        ->and($official->is_test)->toBeFalse()
        ->and($official->replaces_certificate_id)->toBe($test->id)
        ->and($official->reissue_reason)->toBe(PROMOTION_REASON)
        ->and($audit->reason)->toBe(PROMOTION_REASON)
        ->and($audit->new_values['reissued_certificates'])->toBe([$official->number]);

    $this->getJson('/api/v1/verify/'.VerificationCode::display($official->verification_code))->assertOk()
        ->assertJsonPath('data.result', 'valid');
});

it('runs once: a second run promotes nothing', function (): void {
    legacyTestTransferRequest();

    $this->artisan('moashir:finance:promote-test-transfers', ['--reason' => PROMOTION_REASON])->assertSuccessful();
    $this->artisan('moashir:finance:promote-test-transfers', ['--reason' => PROMOTION_REASON])
        ->expectsOutputToContain('Promoted 0 bank transfers.')
        ->assertSuccessful();

    expect(Audit::query()->where('event', AuditEvent::BankTransferPromotedToLive->value)->count())->toBe(1)
        ->and(Certificate::query()->count())->toBe(2);
});

it('leaves test card payments and the transfers of demo entities untouched', function (): void {
    $demo = legacyTestTransferRequest();
    DemoRecord::query()->create(['kind' => DemoRecordKind::Entity, 'record_id' => $demo->entity_id]);
    $card = storedPaidPayment(entityWithOwner(), IntegrationMode::Test);

    $this->artisan('moashir:finance:promote-test-transfers', ['--reason' => PROMOTION_REASON])
        ->expectsOutputToContain('Promoted 0 bank transfers.')
        ->assertSuccessful();

    expect(Payment::query()->findOrFail($demo->payment_id)->mode)->toBe(IntegrationMode::Test)
        ->and($card->refresh()->mode)->toBe(IntegrationMode::Test)
        ->and(Certificate::query()->sole()->is_test)->toBeTrue();
});

it('promotes a reopened request without touching its suspended test certificate, whose next issue is official', function (): void {
    $assessor = assessorUser();
    $assessment = legacyTestTransferRequest($assessor);
    $suspended = Certificate::query()->sole();
    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/reopen", ['reason' => PROMOTION_REASON])->assertOk();
    app('auth')->forgetGuards();

    $this->artisan('moashir:finance:promote-test-transfers', ['--reason' => PROMOTION_REASON])
        ->expectsOutputToContain('Promoted 1 bank transfers.')
        ->assertSuccessful();

    $audit = Audit::query()->where('event', AuditEvent::BankTransferPromotedToLive->value)->sole();
    expect(Payment::query()->findOrFail($assessment->payment_id)->mode)->toBe(IntegrationMode::Live)
        ->and($suspended->refresh()->status)->toBe(CertificateStatus::Suspended)
        ->and($suspended->is_test)->toBeTrue()
        ->and($audit->new_values['reissued_certificates'])->toBe([])
        ->and($audit->new_values['skipped_certificates'])->toBe([['number' => $suspended->number, 'reason' => 'assessment_not_completed']]);

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk();
    $official = Certificate::query()->where('status', CertificateStatus::Issued)->sole();

    expect($official->is_test)->toBeFalse()
        ->and($official->replaces_certificate_id)->toBe($suspended->id);
});

it('promotes a draft request that has no certificate yet', function (): void {
    $assessment = legacyTestTransferRequest(completed: false);

    $this->artisan('moashir:finance:promote-test-transfers', ['--reason' => PROMOTION_REASON])
        ->expectsOutputToContain('Promoted 1 bank transfers.')
        ->assertSuccessful();

    expect(Payment::query()->findOrFail($assessment->payment_id)->mode)->toBe(IntegrationMode::Live)
        ->and(Certificate::query()->count())->toBe(0)
        ->and(Audit::query()->where('event', AuditEvent::BankTransferPromotedToLive->value)->sole()->new_values['skipped_certificates'])->toBe([]);
});

it('promotes a rejected transfer that never opened a request', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $payment = submittedTransfer($owner, $account);
    rejectTransfer(transferReviewer(), $payment)->assertOk();
    Payment::query()->whereKey($payment->id)->update(['mode' => IntegrationMode::Test->value]);
    app('auth')->forgetGuards();

    $this->artisan('moashir:finance:promote-test-transfers', ['--reason' => PROMOTION_REASON])
        ->expectsOutputToContain('Promoted 1 bank transfers.')
        ->assertSuccessful();

    expect($payment->refresh()->mode)->toBe(IntegrationMode::Live);
});

it('reports a payment that fails, leaves it unchanged and still promotes the others', function (): void {
    $version = publishedAccreditedCriteria();
    $broken = legacyTestTransferRequest(version: $version);
    $healthy = legacyTestTransferRequest(version: $version);
    $brokenReference = Payment::query()->findOrFail($broken->payment_id)->reference;
    $broken->entity->delete();

    $this->artisan('moashir:finance:promote-test-transfers', ['--reason' => PROMOTION_REASON])
        ->expectsOutputToContain('Promoted 1 bank transfers.')
        ->expectsOutputToContain($brokenReference)
        ->assertFailed();

    expect(Payment::query()->findOrFail($broken->payment_id)->mode)->toBe(IntegrationMode::Test)
        ->and(Payment::query()->findOrFail($healthy->payment_id)->mode)->toBe(IntegrationMode::Live)
        ->and(Audit::query()->where('event', AuditEvent::BankTransferPromotedToLive->value)->count())->toBe(1);
});
