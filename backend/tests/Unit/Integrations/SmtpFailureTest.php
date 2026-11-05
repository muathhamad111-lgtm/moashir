<?php

declare(strict_types=1);

use App\Enums\Integrations\ProviderErrorKind;
use App\Support\Integrations\SmtpFailure;
use Symfony\Component\Mailer\Exception\TransportException;

it('classifies SMTP transport failures', function (string $message, int $code, ProviderErrorKind $kind): void {
    expect(SmtpFailure::kindOf(new TransportException($message, $code)))->toBe($kind);
})->with([
    'rejected credentials' => ['Expected response code "235" but got code "535", with message "535 Authentication failed".', 535, ProviderErrorKind::AuthFailed],
    'blocked port on an implicit TLS host' => ['Connection could not be established with host "ssl://62.149.100.10:465": stream_socket_client(): Unable to connect to ssl://62.149.100.10:465 (Connection timed out)', 0, ProviderErrorKind::Timeout],
    'certificate mismatch' => ['Unable to connect with STARTTLS: stream_socket_enable_crypto(): Peer certificate CN did not match expected CN.', 0, ProviderErrorKind::Misconfigured],
    'refused connection' => ['Connection could not be established with host "62.149.100.10:587": Connection refused', 0, ProviderErrorKind::Unavailable],
]);
