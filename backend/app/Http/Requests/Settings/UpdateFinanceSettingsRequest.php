<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Data\Settings\FinanceSettingsUpdate;
use App\Data\Settings\VatRegistrationNumberEdit;
use App\Enums\Identity\Ability;
use App\Enums\Settings\FinanceSettingField;
use App\Models\Identity\User;
use App\Rules\VatRatePercent;
use App\Services\Finance\FinanceSettings;
use App\Support\ValueObjects\VatRate;
use App\Support\ValueObjects\VatRegistrationNumber;
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
            FinanceSettingField::ACCREDITED_PRICE_MIRROR => ['prohibited'],
            FinanceSettingField::BAG_PRICE_MIRROR => ['prohibited'],
            FinanceSettingField::VatRate->value => ['sometimes', 'required', new VatRatePercent],
            FinanceSettingField::OrderValidityMinutes->value => ['sometimes', 'required', 'integer', 'between:'.$limits['min'].','.$limits['max']],
            FinanceSettingField::VatRegistrationNumber->value => ['sometimes', 'nullable', 'string', 'regex:'.VatRegistrationNumber::PATTERN],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function toUpdate(): FinanceSettingsUpdate
    {
        $vatRate = FinanceSettingField::VatRate->value;
        $validity = FinanceSettingField::OrderValidityMinutes->value;

        return new FinanceSettingsUpdate(
            $this->filled($vatRate) ? new VatRate($this->string($vatRate)->toString()) : null,
            $this->filled($validity) ? $this->integer($validity) : null,
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
            $this->vatRegistrationNumberEdit(),
        );
    }

    private function vatRegistrationNumberEdit(): ?VatRegistrationNumberEdit
    {
        $field = FinanceSettingField::VatRegistrationNumber->value;

        if (! $this->has($field)) {
            return null;
        }

        return new VatRegistrationNumberEdit($this->filled($field) ? new VatRegistrationNumber($this->string($field)->toString()) : null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_fill_keys(
            array_map(static fn (string $field): string => $field.'.prohibited', FinanceSettingField::priceMirrors()),
            __('validation.custom.price.moved'),
        );
    }
}
