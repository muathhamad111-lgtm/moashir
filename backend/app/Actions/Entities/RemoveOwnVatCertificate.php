<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Entities\EntityVatCertificateDetacher;
use App\Services\Entities\OwnEntity;
use Illuminate\Support\Facades\DB;

final readonly class RemoveOwnVatCertificate
{
    public function __construct(private OwnEntity $ownEntity, private EntityVatCertificateDetacher $certificates) {}

    public function handle(User $owner): Entity
    {
        $entity = $this->ownEntity->of($owner);

        DB::transaction(function () use ($entity): void {
            $locked = Entity::query()->whereKey($entity->id)->lockForUpdate()->firstOrFail();
            $this->certificates->detach($locked);
            $locked->save();
        });

        return $entity->refresh();
    }
}
