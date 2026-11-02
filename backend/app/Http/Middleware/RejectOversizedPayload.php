<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RejectOversizedPayload
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $maxBytes): Response
    {
        $limit = (int) $maxBytes;
        $declared = $request->headers->get('Content-Length');

        if ((is_numeric($declared) && (int) $declared > $limit) || strlen($request->getContent()) > $limit) {
            throw new HttpException(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return $next($request);
    }
}
