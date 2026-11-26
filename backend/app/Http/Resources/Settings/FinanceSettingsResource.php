<?php

declare(strict_types=1);

namespace App\Http\Resources\Settings;

use App\Data\Settings\FinanceSettingsView;
use App\Enums\Settings\FinanceSettingField;
use App\Support\Finance\PriceQuotePresenter;
use App\Support\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read FinanceSettingsView $resource
 */
final class FinanceSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = $this->resource;

        return [
            'currency' => Money::SAUDI_RIYAL,
            FinanceSettingField::ACCREDITED_PRICE_MIRROR => $view->accredited?->priceBeforeVat->amount,
            FinanceSettingField::BAG_PRICE_MIRROR => $view->bag?->priceBeforeVat->amount,
            FinanceSettingField::VatRate->value => $view->vatRate->percent,
            FinanceSettingField::OrderValidityMinutes->value => $view->orderValidityMinutes,
            'order_validity_limits' => $view->orderValidityLimits,
            FinanceSettingField::VatRegistrationNumber->value => $view->vatRegistrationNumber?->value,
            'vat_registration_reminder' => $view->needsVatRegistrationReminder(),
            'pricing' => [
                'accredited' => PriceQuotePresenter::present($view->accredited),
                'bag' => PriceQuotePresenter::present($view->bag),
            ],
            'editable_fields' => array_map(static fn (FinanceSettingField $field): string => $field->value, $view->editableFields),
        ];
    }
}
