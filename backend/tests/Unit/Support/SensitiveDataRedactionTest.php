<?php

declare(strict_types=1);

use App\Support\Audit\AuditValueRedactor;
use App\Support\Monitoring\RedactSensitiveLogData;
use App\Support\Monitoring\SensitiveLogProcessor;
use App\Support\Security\SensitiveUrl;
use Monolog\Level;
use Monolog\LogRecord;

it('redacts the token segment of reset, invitation and other long random path segments', function (string $text, string $expected): void {
    expect(SensitiveUrl::redact($text))->toBe($expected);
})->with([
    'reset link' => ['https://moashir.example.sa/auth/reset-password/Q1w2E3r4T5y6U7i8O9p0A1s2D3f4G5h6J7k8L9z0', 'https://moashir.example.sa/auth/reset-password/[redacted]'],
    'invitation acceptance' => ['/api/v1/auth/invitations/abc/accept', '/api/v1/auth/invitations/[redacted]/accept'],
    'long random segment' => ['/api/v1/things/'.str_repeat('a9', 20).'/x', '/api/v1/things/[redacted]/x'],
    'uuid kept' => ['/api/v1/files/6f1c2b8e-1d3a-4c2e-9e5b-0a7d8c9b1e22/download', '/api/v1/files/6f1c2b8e-1d3a-4c2e-9e5b-0a7d8c9b1e22/download'],
    'short segment kept' => ['/api/v1/entities/42/profile-changes', '/api/v1/entities/42/profile-changes'],
    'inside a sentence' => ['Link: https://moashir.example.sa/auth/invitations/'.str_repeat('Zz', 32).' sent', 'Link: https://moashir.example.sa/auth/invitations/[redacted] sent'],
]);

it('redacts tokens from log messages and nested context before any handler writes them', function (): void {
    $token = str_repeat('Kq7', 22);
    $record = new LogRecord(new DateTimeImmutable, 'production', Level::Warning, 'Invitation lookup failed for /auth/invitations/'.$token, [
        'url' => 'https://moashir.example.sa/api/v1/auth/invitations/'.$token.'/accept',
        'nested' => ['link' => '/auth/reset-password/'.$token],
        'count' => 3,
    ]);

    $processor = new SensitiveLogProcessor;
    $processed = $processor($record);

    expect($processed->message)->toBe('Invitation lookup failed for /auth/invitations/[redacted]')
        ->and($processed->context)->toBe([
            'url' => 'https://moashir.example.sa/api/v1/auth/invitations/[redacted]/accept',
            'nested' => ['link' => '/auth/reset-password/[redacted]'],
            'count' => 3,
        ]);
});

it('taps the redaction onto the application log channels but not onto the demo mail log', function (): void {
    foreach (['single', 'daily', 'stderr'] as $channel) {
        expect(config("logging.channels.{$channel}.tap"))->toContain(RedactSensitiveLogData::class);
    }

    expect(config('logging.channels.mail.tap'))->toBeNull();
});

it('masks secret keys of audit values at any depth and keeps the rest', function (): void {
    expect(AuditValueRedactor::redact([
        'name' => 'جمعية الأمل',
        'password' => 'x',
        'settings' => ['webhook_secret' => 'y', 'api_key' => 'z', 'last4' => '1234', 'bearer_token' => 'w'],
        'recovery_codes' => ['a', 'b'],
    ]))->toBe([
        'name' => 'جمعية الأمل',
        'password' => '[redacted]',
        'settings' => ['webhook_secret' => '[redacted]', 'api_key' => '[redacted]', 'last4' => '1234', 'bearer_token' => '[redacted]'],
        'recovery_codes' => '[redacted]',
    ])->and(AuditValueRedactor::redact(null))->toBeNull();
});
