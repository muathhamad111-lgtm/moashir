<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use App\Support\Localization\LocaleNegotiator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @param  array<string, string>  $cookies
 */
function negotiatedLocale(?string $acceptLanguage, array $cookies = []): string
{
    $request = Request::create('/api/v1/health', 'GET', [], $cookies);
    $request->headers->remove('Accept-Language');

    if ($acceptLanguage !== null) {
        $request->headers->set('Accept-Language', $acceptLanguage);
    }

    return (string) app(SetLocale::class)->handle($request, static fn (): Response => new Response)->headers->get('Content-Language');
}

it('defaults to Arabic', function (): void {
    expect(negotiatedLocale(null))->toBe('ar');
});

it('uses a supported language cookie for guests', function (): void {
    expect(negotiatedLocale(null, [LocaleNegotiator::COOKIE => 'en']))->toBe('en')
        ->and(negotiatedLocale(null, [LocaleNegotiator::COOKIE => 'fr']))->toBe('ar');
});

it('lets the Accept-Language header win over the cookie', function (): void {
    expect(negotiatedLocale('en-GB,en;q=0.9', [LocaleNegotiator::COOKIE => 'ar']))->toBe('en')
        ->and(negotiatedLocale('ar-SA', [LocaleNegotiator::COOKIE => 'en']))->toBe('ar');
});

it('picks the first supported language by quality', function (string $header, string $expected): void {
    expect(negotiatedLocale($header))->toBe($expected);
})->with([
    'quality order' => ['fr;q=1, en;q=0.8, ar;q=0.5', 'en'],
    'upper case tag' => ['EN-US', 'en'],
    'wildcard then arabic' => ['*, ar;q=0.4', 'ar'],
    'refused language' => ['en;q=0, ar;q=0.2', 'ar'],
]);

it('falls back to the cookie when the header names no supported language', function (): void {
    expect(negotiatedLocale('fr-FR, de;q=0.8', [LocaleNegotiator::COOKIE => 'en']))->toBe('en')
        ->and(negotiatedLocale('', [LocaleNegotiator::COOKIE => 'en']))->toBe('en')
        ->and(negotiatedLocale('fr-FR'))->toBe('ar');
});
