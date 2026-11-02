<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use function Sentry\configureScope;

use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

final readonly class AssignRequestId
{
    public const string HEADER = 'X-Request-Id';

    public function __construct(private RequestContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->context->useRequestId((string) $request->headers->get(self::HEADER, ''));
        $requestId = $this->context->requestId();

        Log::shareContext(['request_id' => $requestId]);
        configureScope(static fn (Scope $scope): Scope => $scope->setTag('request_id', $requestId));

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
