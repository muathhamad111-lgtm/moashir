<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Models\Entities\EntityProfileChangeRequest;

/**
 * A change request with the fields whose approved value moved on since the entity proposed them, and the regions
 * and cities its values name.
 */
final readonly class ProfileChangeDetail
{
    /**
     * @param  list<string>  $staleFields
     */
    public function __construct(
        public EntityProfileChangeRequest $request,
        public array $staleFields,
        public ProfileChangePlaces $places,
    ) {}
}
