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
use App\Models\Finance\Payment;
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
function legacyTestTransferRequest(): Assessment
{
    $assessor = assessorUser();
    $assessment = scoredAccreditedReview(publishedAccreditedCriteria(), $assessor);
    Payment::query()->whereKey($assessment->payment_id)->update([
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
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk();
    app('auth')->forgetGuards();

    return $assessment->refresh();
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
