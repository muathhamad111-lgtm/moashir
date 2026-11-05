<?php

declare(strict_types=1);

use App\Support\Security\SessionCookieName;
use Symfony\Component\HttpFoundation\Cookie;

it('sends a __Host- session cookie that is secure, host-only and on the root path and keeps the CSRF cookie name', function (): void {
    config()->set('session.secure', true);
    config()->set('session.domain');
    config()->set('session.cookie', SessionCookieName::resolve('moashir_production_session', true, null, '/'));

    $cookies = collect($this->get('https://localhost/sanctum/csrf-cookie', ['Referer' => 'https://localhost/', 'Accept' => 'application/json'])->headers->getCookies())
        ->keyBy(static fn (Cookie $cookie): string => $cookie->getName());
    $session = $cookies->get('__Host-moashir_production_session');

    expect($session)->toBeInstanceOf(Cookie::class)
        ->and($session?->isSecure())->toBeTrue()
        ->and($session?->isHttpOnly())->toBeTrue()
        ->and($session?->getPath())->toBe('/')
        ->and($session?->getDomain())->toBeNull()
        ->and($cookies->has('XSRF-TOKEN'))->toBeTrue();
});
