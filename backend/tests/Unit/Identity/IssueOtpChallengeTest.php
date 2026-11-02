<?php

declare(strict_types=1);

use App\Actions\Identity\IssueOtpChallenge;
use App\Data\Identity\OtpDelivery;
use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\OtpPurpose;
use App\Models\Identity\OtpCode;
use Illuminate\Support\Facades\Notification;

it('stores only a hash and a masked destination and voids the previous open code', function (): void {
    Notification::fake();
    $user = entityUser();
    $issue = app(IssueOtpChallenge::class);

    $first = $issue->handle(new OtpDelivery($user, OtpChannel::Email, OtpPurpose::Login));
    $second = $issue->handle(new OtpDelivery($user, OtpChannel::Email, OtpPurpose::Login));

    $stored = OtpCode::query()->where('challenge_id', $second->challengeId)->firstOrFail();
    expect($stored->code_hash)->toHaveLength(64)
        ->and($stored->destination_masked)->toContain('***@')
        ->and($stored->destination_masked)->not->toBe($user->email)
        ->and(OtpCode::query()->where('challenge_id', $first->challengeId)->value('voided_at'))->not->toBeNull()
        ->and($second->expiresAt->diffInSeconds($second->resendAvailableAt->subSeconds(60)->addSeconds(300), true))->toBeLessThan(2.0);
});
