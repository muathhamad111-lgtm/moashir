<?php

declare(strict_types=1);

use App\Support\Monitoring\SentryEventScrubber;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\UserDataBag;

it('removes personal data before an event leaves the server', function (): void {
    $event = Event::createEvent();
    $event->setUser(UserDataBag::createFromUserIdentifier('15'));
    $event->setMessage('Login failed for sara.alotaibi@moashir.example.sa from +966512345678');
    $event->setExceptions([new ExceptionDataBag(new RuntimeException('National id 1012345678 uploaded tax-certificate.pdf'))]);
    $event->setRequest([
        'method' => 'POST',
        'url' => 'https://moashir.ttshub.live/api/v1/auth/login?email=sara@moashir.example.sa',
        'data' => ['password' => 'Secret-Value-2026'],
        'cookies' => ['moashir-session' => 'abc'],
        'headers' => ['cookie' => 'abc', 'x-xsrf-token' => 'token', 'accept' => 'application/json'],
    ]);
    $event->setBreadcrumb([new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'log', 'OTP sent to 0551234567')]);

    $scrubbed = SentryEventScrubber::scrub($event);

    expect($scrubbed->getUser())->toBeNull()
        ->and($scrubbed->getMessage())->toBe('Login failed for [redacted] from [redacted]')
        ->and($scrubbed->getExceptions()[0]->getValue())->not->toContain('1012345678')->not->toContain('tax-certificate')
        ->and($scrubbed->getRequest())->toBe(['method' => 'POST', 'url' => 'https://moashir.ttshub.live/api/v1/auth/login', 'headers' => ['accept' => 'application/json']])
        ->and($scrubbed->getBreadcrumbs()[0]->getMessage())->toBe('OTP sent to [redacted]');
});

it('never sends default personal data', function (): void {
    expect(config('sentry.send_default_pii'))->toBeFalse()
        ->and(config('sentry.before_send'))->toBe([SentryEventScrubber::class, 'scrub']);
});

it('removes single-use tokens from request paths, messages and breadcrumbs', function (): void {
    $token = str_repeat('Ab3', 22);
    $event = Event::createEvent();
    $event->setMessage('Invitation failed at /api/v1/auth/invitations/'.$token.'/accept');
    $event->setRequest(['method' => 'POST', 'url' => 'https://moashir.ttshub.live/api/v1/auth/invitations/'.$token.'/accept']);
    $event->setBreadcrumb([new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_HTTP, 'http', null, ['url' => 'https://moashir.ttshub.live/auth/reset-password/'.$token])]);

    $scrubbed = SentryEventScrubber::scrub($event);

    expect($scrubbed->getRequest()['url'])->toBe('https://moashir.ttshub.live/api/v1/auth/invitations/[redacted]/accept')
        ->and($scrubbed->getMessage())->not->toContain($token)
        ->and($scrubbed->getBreadcrumbs()[0]->getMetadata()['url'])->toBe('https://moashir.ttshub.live/auth/reset-password/[redacted]')
        ->and(config('sentry.before_send_transaction'))->toBe([SentryEventScrubber::class, 'scrub']);
});
