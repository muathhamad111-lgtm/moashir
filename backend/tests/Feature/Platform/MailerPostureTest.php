<?php

declare(strict_types=1);

use App\Models\Identity\User;
use App\Notifications\Identity\PasswordResetLinkNotification;
use App\Services\Platform\MailerPosture;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;

function enforceMailerIn(string $environment, string $mailer, bool $demo = false): void
{
    app()['env'] = $environment;
    config()->set('mail.default', $mailer);
    config()->set('moashir.demo.posture', $demo);

    app(MailerPosture::class)->enforce();
}

it('refuses the log and array mailers in a real production and sends through the platform SMTP settings instead', function (string $mailer): void {
    Log::spy();

    enforceMailerIn('production', $mailer);

    expect(config('mail.default'))->toBe('settings');
    Log::shouldHaveReceived('critical')->with('platform.mailer_refused', ['mailer' => $mailer, 'replacement' => 'settings'])->once();
})->with(['log', 'array']);

it('refuses a failover mailer that would fall back to the log', function (): void {
    enforceMailerIn('production', 'failover');

    expect(config('mail.default'))->toBe('settings');
});

it('keeps the log mailer in the demo posture, in staging and in local development', function (string $environment, bool $demo): void {
    enforceMailerIn($environment, 'log', $demo);

    expect(config('mail.default'))->toBe('log');
})->with([
    'production demo' => ['production', true],
    'staging' => ['staging', false],
    'local' => ['local', false],
]);

it('fails closed instead of writing an e-mail to the logs when SMTP is not configured', function (): void {
    Log::spy();
    enforceMailerIn('production', 'log');
    $user = User::factory()->create();

    expect(fn () => $user->notifyNow(new PasswordResetLinkNotification('reset-token-value', 60)))->toThrow(TransportException::class, 'E-mail delivery is not configured.');
    Log::shouldNotHaveReceived('debug');
});
