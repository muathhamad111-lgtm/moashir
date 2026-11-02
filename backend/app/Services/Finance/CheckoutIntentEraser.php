<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Contracts\Entities\EntityErasureStep;
use App\Models\Entities\Entity;
use App\Models\Finance\CheckoutIntent;

final class CheckoutIntentEraser implements EntityErasureStep
{
    public function erase(Entity $entity): void
    {
        CheckoutIntent::query()->where('entity_id', $entity->id)->delete();
    }
}
