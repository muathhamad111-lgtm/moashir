<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Http\Middleware\ApplyUserLocale;
use App\Models\Identity\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

function localeAfterUserStep(?User $user, ?string $acceptLanguage): string
{
    App::setLocale('ar');

    $request = Request::create('/api/v1/auth/session');
    $request->headers->remove('Accept-Language');

    if ($acceptLanguage !== null) {
        $request->headers->set('Accept-Language', $acceptLanguage);
    }

    $request->setUserResolver(static fn (): ?User => $user);

    app(ApplyUserLocale::class)->handle($request, static fn (): Response => new Response);

    return App::getLocale();
}

it('applies the saved profile language when the request names none', function (): void {
    $user = User::factory()->withRole(RoleKey::Entity)->make(['locale' => 'en']);

    expect(localeAfterUserStep($user, null))->toBe('en')
        ->and(localeAfterUserStep($user, 'fr'))->toBe('en');
});

it('keeps the Accept-Language header ahead of the profile language', function (): void {
    $user = User::factory()->withRole(RoleKey::Entity)->make(['locale' => 'en']);

    expect(localeAfterUserStep($user, 'ar'))->toBe('ar');
});

it('leaves the negotiated language untouched for guests', function (): void {
    expect(localeAfterUserStep(null, null))->toBe('ar');
});
