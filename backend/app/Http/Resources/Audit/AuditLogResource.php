<?php

declare(strict_types=1);

namespace App\Http\Resources\Audit;

use App\Models\Audit\Audit;
use App\Models\Identity\User;
use App\Services\Audit\AuditLogPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * @property-read Audit $resource
 */
final class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        if (! $viewer instanceof User) {
            throw new LogicException('Audit entries are shaped for a signed-in reader only.');
        }

        return app(AuditLogPresenter::class)->present($this->resource, $viewer);
    }
}
