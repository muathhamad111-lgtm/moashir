<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Identity\AuthenticationRejected;
use App\Models\Identity\User;
use App\Services\Identity\NationalIdRequirement;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureNationalIdProvided
{
    public function __construct(private NationalIdRequirement $requirement) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $this->requirement->isMissingFor($user)) {
            throw AuthenticationRejected::nationalIdRequired();
        }

        return $next($request);
    }
}
