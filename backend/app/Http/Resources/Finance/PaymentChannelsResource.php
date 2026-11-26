<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Data\Finance\PaymentChannelsView;
use App\Enums\Finance\PaymentChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PaymentChannelsView $resource
 */
final class PaymentChannelsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = $this->resource;

        return [
            'channels' => $this->valuesOf($view->enabled),
            'available_channels' => $this->valuesOf($view->available),
            'gateway_ready' => $view->isGatewayReady,
            'active_bank_accounts_count' => $view->activeBankAccountsCount,
            'reviewers_count' => $view->reviewersCount,
        ];
    }

    /**
     * @param  list<PaymentChannel>  $channels
     * @return list<string>
     */
    private function valuesOf(array $channels): array
    {
        return array_map(static fn (PaymentChannel $channel): string => $channel->value, $channels);
    }
}
