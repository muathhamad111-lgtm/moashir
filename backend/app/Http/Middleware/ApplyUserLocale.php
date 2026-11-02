<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Identity\User;
use App\Support\Localization\LocaleNegotiator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApplyUserLocale
{
    public function __construct(private LocaleNegotiator $negotiator) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->negotiator->fromHeader($request) !== null) {
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof User) {
            App::setLocale($user->locale->value);
        }

        return $next($request);
    }
}
