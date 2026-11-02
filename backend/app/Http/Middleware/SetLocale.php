<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Localization\LocaleNegotiator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final readonly class SetLocale
{
    public function __construct(private LocaleNegotiator $negotiator) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->negotiator->forGuest($request));

        $response = $next($request);
        $response->headers->set('Content-Language', App::getLocale());

        return $response;
    }
}
