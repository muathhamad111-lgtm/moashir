<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Integrations\Fakes\FakeFileScanner;
use App\Models\Audit\Audit;
use App\Models\Files\StoredFile;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

const INFECTED_EVIDENCE_NAME = 'إيصال-تحويل-جمعية-الأمل-الخيرية.pdf';

const INFECTED_EVIDENCE_CONTENTS = "%PDF-1.4\n".FakeFileScanner::TEST_SIGNATURE."\n%%EOF";

const APPLE_PAY_UPLOAD_ENDPOINT = '/api/v1/settings/payment/apple-pay/association-file';

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

function refundablePaymentForUploads(): Payment
{
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();

    return paidPayment($owner, $gateway);
}

function uploadRefundEvidence(Payment $payment, User $recorder, ?string $contents = null): TestResponse
{
    $evidence = UploadedFile::fake()->createWithContent(INFECTED_EVIDENCE_NAME, $contents ?? INFECTED_EVIDENCE_CONTENTS);

    return switchTo($recorder)->post(
        "/api/v1/finance/payments/{$payment->public_id}/refunds",
        refundPayload($payment, ['evidence' => $evidence]),
        ['Accept' => 'application/json'],
    );
}

function infectedUploadAlertsSentTo(User $user): int
{
    return Notification::sent($user, EntityNotification::class)
        ->filter(static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::RequestProcessingFailed)
        ->count();
}

function refusalAudits(): int
{
    return Audit::query()->where('event', AuditEvent::InfectedUploadRefused->value)->count();
}

it('alerts the System Administrators and audits the refusal of an infected refund receipt', function (): void {
    $payment = refundablePaymentForUploads();
    $admins = [systemAdmin(), systemAdmin()];
    $finance = financeOfficer();
    $superAdmin = superAdmin();

    uploadRefundEvidence($payment, $finance)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file.infected');

    $refused = StoredFile::withTrashed()->ofPurpose(StoredFilePurpose::RefundEvidence)->sole();
    $audit = Audit::query()->where('event', AuditEvent::InfectedUploadRefused->value)->sole();

    expect($refused->trashed())->toBeTrue()
        ->and(Storage::disk('local')->allFiles('files/refund-evidence'))->toBe([])
        ->and($audit->auditable_id)->toBe($refused->id)
        ->and($audit->user_id)->toBe($finance->id)
        ->and($audit->old_values)->toBe([])
        ->and($audit->new_values)->toBe(['purpose' => 'refund_evidence', 'size_bytes' => strlen(INFECTED_EVIDENCE_CONTENTS)])
        ->and(json_encode($audit->toArray(), JSON_UNESCAPED_UNICODE))->not->toContain('إيصال-تحويل');

    foreach ($admins as $admin) {
        Notification::assertSentTo($admin, EntityNotification::class, static fn (EntityNotification $notification, array $channels): bool => $notification->notice->code === NotificationCode::RequestProcessingFailed
            && $channels === [InAppChannel::class]
            && str_contains($notification->notice->body->en, 'bank-transfer receipt of a refund')
            && str_contains($notification->notice->body->ar, 'إيصال التحويل البنكي لعملية استرداد')
            && ! str_contains($notification->notice->title->ar.$notification->notice->body->ar.$notification->notice->body->en, 'إيصال-تحويل')
            && ! str_contains($notification->notice->body->en, $refused->public_id));
    }

    Notification::assertNotSentTo($finance, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::RequestProcessingFailed);
    Notification::assertNotSentTo($superAdmin, EntityNotification::class);
});

it('throttles the alert per purpose like the background scan alert while auditing every refusal', function (): void {
    $payment = refundablePaymentForUploads();
    $admin = systemAdmin();
    $finance = financeOfficer();

    uploadRefundEvidence($payment, $finance)->assertUnprocessable();
    uploadRefundEvidence($payment, $finance)->assertUnprocessable();

    expect(infectedUploadAlertsSentTo($admin))->toBe(1)
        ->and(refusalAudits())->toBe(2);

    switchTo($admin)->post(APPLE_PAY_UPLOAD_ENDPOINT, [
        'file' => UploadedFile::fake()->createWithContent('apple-developer-merchantid-domain-association', '7B22'.FakeFileScanner::TEST_SIGNATURE."\n")->mimeType('text/plain'),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonPath('code', 'file.infected');

    expect(infectedUploadAlertsSentTo($admin))->toBe(2)
        ->and(Audit::query()->where('event', AuditEvent::InfectedUploadRefused->value)->latest('id')->firstOrFail()->new_values['purpose'] ?? null)->toBe('apple_pay_domain_association');

    $this->travel(config('moashir.integrations.alert_cooldown_minutes') + 1)->minutes();
    uploadRefundEvidence($payment, staffUser(RoleKey::FinanceOfficer))->assertUnprocessable();

    expect(infectedUploadAlertsSentTo($admin))->toBe(3)
        ->and(refusalAudits())->toBe(4);
});

it('raises no infected-upload alert for a clean upload or a failed scan', function (string $case): void {
    $payment = refundablePaymentForUploads();
    $admin = systemAdmin();

    if ($case === 'scanner unavailable') {
        app(FakeFileScanner::class)->makeUnavailable();
    }

    $response = uploadRefundEvidence($payment, financeOfficer(), "%PDF-1.4\n%Moashir bank transfer receipt\n%%EOF\n");

    $case === 'clean'
        ? $response->assertCreated()
        : $response->assertUnprocessable()->assertJsonPath('code', 'file.scan_failed');

    expect(infectedUploadAlertsSentTo($admin))->toBe(0)
        ->and(refusalAudits())->toBe(0);
})->with(['clean', 'scanner unavailable']);
