<?php

declare(strict_types=1);

namespace App\Http\Resources\Certificates;

use App\Enums\Certificates\CertificateLevel;
use App\Support\ValueObjects\LevelThresholds;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read LevelThresholds $resource
 */
final class CertificateLevelsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $minimums = $this->resource->toArray();
        $levels = [];

        foreach (CertificateLevel::ascending() as $level) {
            $levels[] = [
                'level' => $level->value,
                'label' => LocalizedText::fromTranslation('certificates.levels.'.$level->value)->toArray(),
                'min_percent' => $minimums[$level->value],
            ];
        }

        return ['levels' => $levels, 'participation_below' => $minimums[CertificateLevel::Bronze->value]];
    }
}
