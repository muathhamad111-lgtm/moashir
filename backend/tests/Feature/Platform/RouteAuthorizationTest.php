<?php

declare(strict_types=1);

use Illuminate\Routing\Route;

const PUBLIC_API_ROUTES = [
    'health',
    'health.ready',
    'auth.login-modes',
    'auth.login',
    'auth.otp.request',
    'auth.otp.verify',
    'auth.otp.resend',
    'auth.mfa.challenge',
    'auth.mfa.enroll',
    'auth.mfa.confirm',
    'auth.password.forgot',
    'auth.password.reset',
    'auth.invitations.accept',
    'content.model-criteria',
    'regions.index',
    'terms.current',
    'auth.register',
    'webhooks.moyasar',
    'content.prices',
    'award.cycles.current',
    'content.status',
    'content.home',
    'content.landing',
    'content.pages.show',
    'content.legal.show',
    'content.files.show',
    'kpis.index',
    'kpis.featured',
    'kpis.show',
    'kpi-categories.index',
    'kpi-types.index',
    'content.assessment-paths.show',
    'certificates.verify',
    'contact-messages.store',
    'content.certificate-levels',
];

/**
 * @return list<Route>
 */
function apiRoutes(): array
{
    return array_values(array_filter(
        app('router')->getRoutes()->getRoutes(),
        static fn (Route $route): bool => str_starts_with($route->uri(), 'api/v1'),
    ));
}

it('names every API route', function (): void {
    foreach (apiRoutes() as $route) {
        expect($route->getName())->not->toBeNull("Route [{$route->uri()}] has no name.");
    }
});

it('authorizes every non-public API route with a can: ability', function (): void {
    foreach (apiRoutes() as $route) {
        if (in_array($route->getName(), PUBLIC_API_ROUTES, true)) {
            continue;
        }

        $middleware = $route->gatherMiddleware();

        expect(in_array('auth:sanctum', $middleware, true))->toBeTrue("Route [{$route->getName()}] is not authenticated.")
            ->and(collect($middleware)->contains(static fn (string $entry): bool => str_starts_with($entry, 'can:')))
            ->toBeTrue("Route [{$route->getName()}] has no can: ability.");
    }
});

it('rate limits every API route', function (): void {
    foreach (apiRoutes() as $route) {
        expect(collect(app('router')->gatherRouteMiddleware($route))->contains(static fn (string $entry): bool => str_contains($entry, 'ThrottleRequests')))
            ->toBeTrue("Route [{$route->getName()}] is not rate limited.");
    }
});

it('keeps the public allow-list free of stale entries', function (): void {
    $names = array_map(static fn (Route $route): ?string => $route->getName(), apiRoutes());

    foreach (PUBLIC_API_ROUTES as $public) {
        expect($names)->toContain($public);
    }
});

it('exposes no generic status patch endpoint', function (): void {
    foreach (apiRoutes() as $route) {
        expect(in_array('PATCH', $route->methods(), true) && str_ends_with($route->uri(), '/status'))->toBeFalse();
    }
});
