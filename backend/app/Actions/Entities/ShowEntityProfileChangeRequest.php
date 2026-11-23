<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Entities\ProfileChangeDetail;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Services\Entities\ProfileChangePlacesLoader;
use App\Services\Entities\ProfileChanges\ProfileChangeDecisionGuard;

final readonly class ShowEntityProfileChangeRequest
{
    public function __construct(private ProfileChangeDecisionGuard $guard, private ProfileChangePlacesLoader $places) {}

    public function handle(EntityProfileChangeRequest $request): ProfileChangeDetail
    {
        $request->loadMissing(['entity.owner', 'entity.logo', 'entity.vatCertificate', 'submitter', 'reviewer', 'pendingLogo', 'pendingVatCertificate']);

        $stale = $request->isPending() ? $this->guard->staleFields($request, $request->entity) : [];

        return new ProfileChangeDetail($request, $stale, $this->places->forRequest($request));
    }
}
