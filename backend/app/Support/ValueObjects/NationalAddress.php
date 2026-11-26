<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Enums\Entities\EntityProfileField;
use InvalidArgumentException;

final readonly class NationalAddress
{
    public const string BUILDING_NUMBER_PATTERN = '/^[0-9]{4}$/';

    public const string POSTAL_CODE_PATTERN = '/^[0-9]{5}$/';

    public const string ADDITIONAL_NUMBER_PATTERN = '/^[0-9]{4}$/';

    public const string SHORT_CODE_PATTERN = '/^[A-Za-z]{4}[0-9]{4}$/';

    public const int STREET_MAX_LENGTH = 150;

    public const int DISTRICT_MAX_LENGTH = 150;

    public const int CITY_MAX_LENGTH = 100;

    public ?string $shortCode;

    public function __construct(
        public string $buildingNumber,
        public string $street,
        public string $district,
        public string $city,
        public string $postalCode,
        public string $additionalNumber,
        ?string $shortCode = null,
    ) {
        $this->assertMatches(self::BUILDING_NUMBER_PATTERN, $buildingNumber, 'building number');
        $this->assertMatches(self::POSTAL_CODE_PATTERN, $postalCode, 'postal code');
        $this->assertMatches(self::ADDITIONAL_NUMBER_PATTERN, $additionalNumber, 'additional number');
        $this->assertFilled($street, 'street');
        $this->assertFilled($district, 'district');
        $this->assertFilled($city, 'city');

        if ($shortCode !== null) {
            $this->assertMatches(self::SHORT_CODE_PATTERN, $shortCode, 'short address');
        }

        $this->shortCode = $shortCode === null ? null : mb_strtoupper($shortCode);
    }

    /**
     * @return array<string, string|null>
     */
    public function toAttributes(): array
    {
        return [
            EntityProfileField::AddressBuildingNumber->value => $this->buildingNumber,
            EntityProfileField::AddressStreet->value => trim($this->street),
            EntityProfileField::AddressDistrict->value => trim($this->district),
            EntityProfileField::AddressCity->value => trim($this->city),
            EntityProfileField::AddressPostalCode->value => $this->postalCode,
            EntityProfileField::AddressAdditionalNumber->value => $this->additionalNumber,
            EntityProfileField::AddressShortCode->value => $this->shortCode,
        ];
    }

    private function assertMatches(string $pattern, string $value, string $part): void
    {
        if (preg_match($pattern, $value) !== 1) {
            throw new InvalidArgumentException('The national address '.$part.' has an invalid format.');
        }
    }

    private function assertFilled(string $value, string $part): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('The national address '.$part.' is required.');
        }
    }
}
