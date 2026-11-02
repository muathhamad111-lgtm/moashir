<?php

declare(strict_types=1);

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\ApplyUserLocale;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnforceSessionTimeouts;
use App\Http\Middleware\EnsureJsonRequest;
use App\Http\Middleware\EnsureMfaSatisfied;
use App\Http\Middleware\EnsurePasswordChangeNotRequired;
use App\Http\Middleware\RejectOversizedPayload;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Spatie\Csp\AddCspHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
        then: static function (): void {
            require __DIR__.'/../routes/well-known.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->prepend([AssignRequestId::class, SetLocale::class]);
        $middleware->append([SetSecurityHeaders::class, AddCspHeaders::class]);
        $middleware->api(append: [EnsureJsonRequest::class, ApplyUserLocale::class]);
        $middleware->appendToPriorityList(after: AuthenticatesRequests::class, append: ApplyUserLocale::class);
        $middleware->throttleApi('api');
        $middleware->alias([
            'session.timeouts' => EnforceSessionTimeouts::class,
            'mfa' => EnsureMfaSatisfied::class,
            'password.current' => EnsurePasswordChangeNotRequired::class,
            'payload.limit' => RejectOversizedPayload::class,
        ]);
        $middleware->redirectGuestsTo(fn (): ?string => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*', 'sanctum/*') || $request->expectsJson(),
        );
        $exceptions->render(
            fn (Throwable $exception, Request $request) => $request->is('api/*', 'sanctum/*')
                ? app(ApiExceptionRenderer::class)->render($exception)
                : null,
        );
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'code', 'recovery_code', 'token']);
    })->create();
