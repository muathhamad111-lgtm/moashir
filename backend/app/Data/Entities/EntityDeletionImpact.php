<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\ErasureRetainedRecord;

final readonly class EntityDeletionImpact
{
    /**
     * @param  list<OpenEntityRequest>  $openRequests
     * @param  list<ErasureRetainedRecord>  $keptOnPermanentDeletion
     */
    public function __construct(
        public array $openRequests,
        public array $keptOnPermanentDeletion,
    ) {}

    /**
     * @return list<string>
     */
    public function openRequestNumbers(): array
    {
        return array_map(static fn (OpenEntityRequest $request): string => $request->number, $this->openRequests);
    }

    public function hasOpenRequests(): bool
    {
        return $this->openRequests !== [];
    }
}
