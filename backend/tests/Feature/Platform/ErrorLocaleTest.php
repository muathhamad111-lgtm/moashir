<?php

declare(strict_types=1);

use App\Enums\Identity\Locale;
use App\Support\Localization\LocaleNegotiator;
use Illuminate\Testing\TestResponse;

dataset('error languages', [
    'Arabic' => ['ar'],
    'English' => ['en'],
]);

function translatedError(string $locale, string $code): string
{
    return (string) __('errors.'.$code, [], $locale);
}

function assertLocalizedError(TestResponse $response, int $status, string $code, string $locale): void
{
    $response->assertStatus($status)
        ->assertHeader('Content-Language', $locale)
        ->assertJsonPath('code', $code)
        ->assertJsonPath('message', translatedError($locale, $code));
}

it('translates 401 on the session endpoint per Accept-Language', function (string $locale): void {
    assertLocalizedError(
        $this->withHeader('Accept-Language', $locale)->getJson('/api/v1/auth/session'),
        401,
        'unauthenticated',
        $locale,
    );
})->with('error languages');

it('translates 401 on protected routes per Accept-Language', function (string $locale, string $uri): void {
    assertLocalizedError($this->withHeader('Accept-Language', $locale)->getJson($uri), 401, 'unauthenticated', $locale);
})->with('error languages')->with([
    'users list' => ['/api/v1/users'],
    'global search' => ['/api/v1/search?q=ab'],
    'own sessions' => ['/api/v1/me/sessions'],
]);

it('shows the expected English and Arabic texts for 401', function (): void {
    $this->withHeader('Accept-Language', 'en')->getJson('/api/v1/auth/session')
        ->assertJsonPath('message', 'Please sign in to continue.');

    $this->withHeader('Accept-Language', 'ar')->getJson('/api/v1/auth/session')
        ->assertJsonPath('message', 'يرجى تسجيل الدخول للمتابعة.');
});

it('falls back to the language cookie and then Arabic when no supported header is sent', function (): void {
    $this->withCredentials()->withHeader('Accept-Language', '')->withUnencryptedCookie(LocaleNegotiator::COOKIE, 'en')
        ->getJson('/api/v1/auth/session')
        ->assertUnauthorized()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('message', translatedError('en', 'unauthenticated'));

    $this->withCredentials()->withHeader('Accept-Language', 'fr-FR')->withUnencryptedCookie(LocaleNegotiator::COOKIE, 'en')
        ->getJson('/api/v1/auth/session')
        ->assertHeader('Content-Language', 'en');

    $this->withCredentials()->withHeader('Accept-Language', '')->withUnencryptedCookie(LocaleNegotiator::COOKIE, 'de')
        ->getJson('/api/v1/auth/session')
        ->assertHeader('Content-Language', 'ar')
        ->assertJsonPath('message', translatedError('ar', 'unauthenticated'));
});

it('translates 419 when the CSRF token is missing', function (string $locale): void {
    app()->instance('env', 'local');

    assertLocalizedError(
        $this->withHeader('Accept-Language', $locale)->postJson('/api/v1/auth/login', ['mode' => 'password', 'email' => 'x@moashir.example.sa', 'password' => 'Wrong-Password-2026!']),
        419,
        'csrf.token_mismatch',
        $locale,
    );
})->with('error languages');

it('translates 429 when a rate limit is hit', function (string $locale): void {
    $attempt = fn (): TestResponse => $this->withHeader('Accept-Language', $locale)
        ->postJson('/api/v1/auth/login', ['mode' => 'password', 'email' => 'limited.'.$locale.'@moashir.example.sa', 'password' => 'Wrong-Password-2026!']);

    foreach (range(1, 5) as $ignored) {
        $attempt();
    }

    assertLocalizedError($attempt(), 429, 'rate_limited', $locale);
})->with('error languages');

it('translates 404 for unknown API paths and 406 for non-JSON requests', function (string $locale): void {
    assertLocalizedError($this->withHeader('Accept-Language', $locale)->getJson('/api/v1/no-such-resource'), 404, 'not_found', $locale);

    assertLocalizedError(
        $this->withHeaders(['Accept-Language' => $locale, 'Accept' => 'text/html'])->get('/api/v1/health'),
        406,
        'request.not_acceptable',
        $locale,
    );
})->with('error languages');

it('uses the saved profile language of a signed-in user before the cookie', function (): void {
    $user = entityUser();
    $user->forceFill(['locale' => Locale::English])->save();

    signIn($user)->withCredentials()->withHeader('Accept-Language', '')->withUnencryptedCookie(LocaleNegotiator::COOKIE, 'ar')
        ->getJson('/api/v1/users')
        ->assertForbidden()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('message', translatedError('en', 'forbidden'));
});

it('keeps Accept-Language ahead of the saved profile language', function (): void {
    $user = entityUser();
    $user->forceFill(['locale' => Locale::English])->save();

    signIn($user)->withHeader('Accept-Language', 'ar')
        ->getJson('/api/v1/users')
        ->assertForbidden()
        ->assertHeader('Content-Language', 'ar')
        ->assertJsonPath('message', translatedError('ar', 'forbidden'));
});
