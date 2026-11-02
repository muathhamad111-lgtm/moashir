<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Data\Integrations\IntegrationSnapshot;
use App\Enums\Integrations\IntegrationKind;
use App\Models\Identity\User;
use App\Services\Integrations\IntegrationSnapshotReader;

final readonly class ListIntegrationOverview
{
    public function __construct(private IntegrationSnapshotReader $reader) {}

    /**
     * @return list<IntegrationSnapshot>
     */
    public function handle(User $actor): array
    {
        $snapshots = [];

        foreach (IntegrationKind::cases() as $kind) {
            if ($actor->can($kind->permission()->value)) {
                $snapshots[] = $this->reader->read($kind);
            }
        }

        return $snapshots;
    }
}
