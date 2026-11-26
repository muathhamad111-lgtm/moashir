<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Files\FileRejected;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Symfony\Component\HttpFoundation\Response;

final readonly class ValidateDownloadSignature
{
    public const string INTENT = 'intent';

    public function __construct(private UrlGenerator $urls) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->urls->hasCorrectSignature($request, ignoreQuery: [self::INTENT])) {
            throw FileRejected::signatureInvalid();
        }

        if (! $this->urls->signatureHasNotExpired($request)) {
            throw FileRejected::signatureExpired();
        }

        return $next($request);
    }
}
