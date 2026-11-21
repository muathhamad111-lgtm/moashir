<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\EntityProfileWrite;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\ProfileSection;
use App\Support\ScalarValue;
use App\Support\ValueObjects\NationalAddress;
use App\Support\ValueObjects\VatRegistrationNumber;

final class UpdateBillingSectionRequest extends ProfileSectionRequest
{
    public function section(): ProfileSection
    {
        return ProfileSection::Billing;
    }

    protected function sectionRules(): array
    {
        return [
            'address_building_number' => ['required', 'string', 'regex:'.NationalAddress::BUILDING_NUMBER_PATTERN],
            'address_street' => ['required', 'string', 'max:'.NationalAddress::STREET_MAX_LENGTH],
            'address_district' => ['required', 'string', 'max:'.NationalAddress::DISTRICT_MAX_LENGTH],
            'address_city' => ['required', 'string', 'max:'.NationalAddress::CITY_MAX_LENGTH],
            'address_postal_code' => ['required', 'string', 'regex:'.NationalAddress::POSTAL_CODE_PATTERN],
            'address_additional_number' => ['required', 'string', 'regex:'.NationalAddress::ADDITIONAL_NUMBER_PATTERN],
            'address_short_code' => ['nullable', 'string', 'regex:'.NationalAddress::SHORT_CODE_PATTERN],
            'vat_registered' => ['required', 'boolean'],
            'vat_number' => [
                'nullable',
                'string',
                'required_if_accepted:vat_registered',
                'prohibited_if_declined:vat_registered',
                'regex:'.VatRegistrationNumber::PATTERN,
            ],
        ];
    }

    protected function write(array $validated): EntityProfileWrite
    {
        $vatRegistered = filter_var($validated['vat_registered'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $vatNumber = $validated['vat_number'] ?? null;

        return new EntityProfileWrite([
            ...$this->addressOf($validated)->toAttributes(),
            EntityProfileField::VatRegistered->value => $vatRegistered,
            EntityProfileField::VatNumber->value => $vatRegistered && is_string($vatNumber) ? new VatRegistrationNumber($vatNumber)->value : null,
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function addressOf(array $validated): NationalAddress
    {
        $shortCode = $validated['address_short_code'] ?? null;

        return new NationalAddress(
            ScalarValue::string($validated['address_building_number'] ?? null),
            ScalarValue::string($validated['address_street'] ?? null),
            ScalarValue::string($validated['address_district'] ?? null),
            ScalarValue::string($validated['address_city'] ?? null),
            ScalarValue::string($validated['address_postal_code'] ?? null),
            ScalarValue::string($validated['address_additional_number'] ?? null),
            is_string($shortCode) && $shortCode !== '' ? $shortCode : null,
        );
    }
}
