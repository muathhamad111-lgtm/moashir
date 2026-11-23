<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Nda\NdaRejected;
use App\Models\Identity\User;
use App\Services\Nda\NdaAcceptanceCheck;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The server-side gate of the staff non-disclosure agreement: an in-scope user who has not accepted the current
 * version is refused every signed-in route except the session, sign-out and the agreement itself. It runs after
 * `mfa` and `password.current`, so the user passes those first.
 */
final readonly class EnsureNdaAccepted
{
    public function __construct(private NdaAcceptanceCheck $check) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $this->check->isRequiredFor($user)) {
            throw NdaRejected::acceptanceRequired();
        }

        return $next($request);
    }
}
