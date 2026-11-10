<?php

declare(strict_types=1);

namespace App\Support\Prices;

use App\Enums\Prices\PriceVersionState;
use App\Models\Finance\ServicePriceVersion;
use App\Models\Identity\User;
use App\Support\ValueObjects\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

final class PriceVersionPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(ServicePriceVersion $version, string $timezone): array
    {
        return [
            'id' => $version->id,
            'price_before_vat' => $version->price_before_vat,
            'currency' => Money::SAUDI_RIYAL,
            'availability' => $version->availability->value,
            'effective_from' => $version->effective_from->toIso8601String(),
            'effective_on' => $version->effective_from->copy()->setTimezone($timezone)->toDateString(),
            'is_scheduled' => $version->isScheduledAt(Date::now()),
            'set_by' => self::userOf($version->setBy),
            'created_at' => $version->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    public static function userOf(?User $user): ?array
    {
        return $user instanceof User ? ['id' => $user->id, 'name' => $user->name] : null;
    }

    public static function stateOf(ServicePriceVersion $version, ?int $inForceId, CarbonInterface $now): PriceVersionState
    {
        return match (true) {
            $version->isCancelled() => PriceVersionState::Cancelled,
            $version->id === $inForceId => PriceVersionState::InForce,
            $version->effective_from->greaterThan($now) => PriceVersionState::Scheduled,
            default => PriceVersionState::Superseded,
        };
    }
}
