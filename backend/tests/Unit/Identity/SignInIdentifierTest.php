<?php

declare(strict_types=1);

use App\Enums\Identity\BlindIndexPurpose;
use App\Enums\Identity\SignInIdentifierType;
use App\Services\Identity\BlindIndex;
use App\Support\Security\SessionCookieName;
use App\Support\ValueObjects\SignInIdentifier;

it('detects the identifier type by its format and normalises it', function (string $input, SignInIdentifierType $type, string $value): void {
    $identifier = SignInIdentifier::tryParse($input);

    expect($identifier?->type)->toBe($type)
        ->and($identifier?->value)->toBe($value);
})->with([
    'e-mail in any case' => [' Info@AlAmal.Example.SA ', SignInIdentifierType::Email, 'info@alamal.example.sa'],
    'local mobile' => ['0551234567', SignInIdentifierType::Mobile, '+966551234567'],
    'international mobile' => ['+966551234567', SignInIdentifierType::Mobile, '+966551234567'],
    'mobile with spaces' => ['055 123 4567', SignInIdentifierType::Mobile, '+966551234567'],
    'unified number' => ['7001234567', SignInIdentifierType::UnifiedNumber, '7001234567'],
]);

it('refuses anything that is neither an e-mail, a Saudi mobile nor a unified number', function (string $input): void {
    expect(SignInIdentifier::tryParse($input))->toBeNull();
})->with(['', 'alamal', 'info@', '0114567890', '6001234567', '70012345678', '+971501234567', '٠٥٥١٢٣٤٥٦٧']);

it('keys the blind index with the application key and separates purposes', function (): void {
    $index = app(BlindIndex::class);
    $phoneIndex = $index->of(BlindIndexPurpose::Phone, '+966551234567');

    expect($phoneIndex)->toMatch('/^[0-9a-f]{64}$/')
        ->and($phoneIndex)->toBe($index->of(BlindIndexPurpose::Phone, '+966551234567'))
        ->and($phoneIndex)->not->toBe(hash('sha256', '+966551234567'))
        ->and($phoneIndex)->not->toBe($index->of(BlindIndexPurpose::SignInIdentifier, '+966551234567'));

    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    expect($index->of(BlindIndexPurpose::Phone, '+966551234567'))->not->toBe($phoneIndex);
});

it('prefixes the session cookie with __Host- only when the cookie is secure, host-only and on the root path', function (bool $secure, ?string $domain, string $path, string $expected): void {
    expect(SessionCookieName::resolve('moashir_production_session', $secure, $domain, $path))->toBe($expected);
})->with([
    'production' => [true, null, '/', '__Host-moashir_production_session'],
    'empty domain' => [true, '', '/', '__Host-moashir_production_session'],
    'local over http' => [false, null, '/', 'moashir_production_session'],
    'shared parent domain' => [true, '.moashir.example.sa', '/', 'moashir_production_session'],
    'sub path' => [true, null, '/api', 'moashir_production_session'],
]);

it('never doubles the prefix and removes it when the cookie cannot carry it', function (): void {
    expect(SessionCookieName::resolve('__Host-moashir-session', true, null, '/'))->toBe('__Host-moashir-session')
        ->and(SessionCookieName::resolve('__Host-moashir-session', false, null, '/'))->toBe('moashir-session');
});
