<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Insights\KitActivation;
use App\Exceptions\Insights\InsightsRejected;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An entity reaches its excellence tools only through an activated Quick Excellence Kit; staff
 * (no entity) pass through to their own read-only authorization.
 */
final readonly class EnsureInsightsKitActive
{
    public function __construct(private KitActivation $kits) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->entity_id !== null && ! $this->unlocks($request, $user->entity_id)) {
            throw InsightsRejected::kitRequired();
        }

        return $next($request);
    }

    private function unlocks(Request $request, int $entityId): bool
    {
        $assessment = $request->route('assessment');

        return $assessment instanceof Assessment ? $this->kits->isActiveFor($assessment) : $this->kits->hasActiveKit($entityId);
    }
}
