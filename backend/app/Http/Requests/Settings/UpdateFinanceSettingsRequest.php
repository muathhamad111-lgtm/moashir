<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Data\Settings\FinanceSettingsUpdate;
use App\Enums\Identity\Ability;
use App\Enums\Settings\FinanceSettingField;
use App\Models\Identity\User;
use App\Rules\PriceBeforeVat;
use App\Rules\VatRatePercent;
use App\Services\Finance\FinanceSettings;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\VatRate;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateFinanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        return array_all(FinanceSettingField::cases(), fn (FinanceSettingField $field): bool => ! $this->has($field->value) || $user->can(Ability::EditFinanceSettingField->value, $field));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $limits = app(FinanceSettings::class)->orderValidityLimits();

        return [
            FinanceSettingField::AccreditedPrice->value => ['sometimes', 'required', new PriceBeforeVat],
            FinanceSettingField::BagPrice->value => ['sometimes', 'required', new PriceBeforeVat],
            FinanceSettingField::VatRate->value => ['sometimes', 'required', new VatRatePercent],
            FinanceSettingField::OrderValidityMinutes->value => ['sometimes', 'required', 'integer', 'between:'.$limits['min'].','.$limits['max']],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function toUpdate(): FinanceSettingsUpdate
    {
        $vatRate = FinanceSettingField::VatRate->value;
        $validity = FinanceSettingField::OrderValidityMinutes->value;

        return new FinanceSettingsUpdate(
            $this->money(FinanceSettingField::AccreditedPrice),
            $this->money(FinanceSettingField::BagPrice),
            $this->filled($vatRate) ? new VatRate($this->string($vatRate)->toString()) : null,
            $this->filled($validity) ? $this->integer($validity) : null,
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }

    private function money(FinanceSettingField $field): ?Money
    {
        return $this->filled($field->value) ? new Money($this->string($field->value)->toString()) : null;
    }
}
