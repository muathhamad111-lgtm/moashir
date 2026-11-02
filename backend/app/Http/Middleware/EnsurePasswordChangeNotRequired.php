<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Identity\AuthenticationRejected;
use App\Models\Identity\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePasswordChangeNotRequired
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->password_change_required) {
            throw AuthenticationRejected::passwordChangeRequired();
        }

        return $next($request);
    }
}
