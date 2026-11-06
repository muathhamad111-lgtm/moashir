<?php

declare(strict_types=1);

namespace App\Support\Entities;

use App\Data\Entities\EntityContactData;
use App\Data\Entities\EntityProfileData;
use App\Data\Entities\EntityRegistryUpdate;
use App\Enums\Entities\DevelopmentSector;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Models\Entities\City;
use App\Models\Entities\Entity;
use App\Models\Entities\Region;
use App\Rules\WebsiteAddress;
use App\Support\ScalarValue;
use App\Support\ValueObjects\EntityName;
use App\Support\ValueObjects\GovernanceGrade;
use App\Support\ValueObjects\LicenseNumber;
use App\Support\ValueObjects\PhoneNumber;
use App\Support\ValueObjects\UnifiedNumber;
use App\Support\ValueObjects\WebsiteUrl;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

final class EntityProfileInput
{
    private const string PHONE_PATTERN = '/^(05\d{8}|\+9665\d{8})$/';

    private const string DATE_FORMAT = 'Y-m-d';

    private const string EARLIEST_FOUNDING_DATE = '1900-01-01';

    /**
     * @return array<string, mixed>
     */
    public static function rules(mixed $regionId): array
    {
        return [
            'name' => ['required', 'array:ar,en'],
            'name.ar' => ['required', 'string', 'max:200'],
            'name.en' => ['nullable', 'string', 'max:200'],
            'authorized_person_name' => ['required', 'string', 'min:3', 'max:150'],
            'phone' => ['nullable', 'string', 'regex:'.self::PHONE_PATTERN],
            'entity_type' => ['required', Rule::enum(EntityType::class)],
            'development_sector' => ['required', Rule::enum(DevelopmentSector::class)],
            'org_size' => ['required', Rule::enum(OrgSize::class)],
            'founding_year' => ['required', 'integer', 'min:1900', 'max:'.Date::now()->year],
            'region_id' => ['required', 'integer', Rule::exists(Region::class, 'id')->where('is_active', true)],
            'city_id' => ['required', 'integer', self::cityOfRegion($regionId)],
            'governance_grade' => ['nullable', 'decimal:0,2', 'between:0,100'],
            'employees_count' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'annual_beneficiaries_count' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'has_strategic_plan' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function registryRules(?int $entityId): array
    {
        $today = Date::now(config()->string('app.display_timezone'))->format(self::DATE_FORMAT);

        return [
            'unified_number' => ['sometimes', ...self::unifiedNumberRules($entityId)],
            'license_number' => ['sometimes', 'nullable', 'string', 'regex:'.LicenseNumber::PATTERN, Rule::unique(Entity::class, 'license_number')->ignore($entityId)],
            'founded_on' => ['sometimes', 'nullable', 'date_format:'.self::DATE_FORMAT, 'after_or_equal:'.self::EARLIEST_FOUNDING_DATE, 'before_or_equal:'.$today],
            'website_url' => ['sometimes', 'nullable', 'string', 'max:'.WebsiteUrl::MAX_LENGTH, new WebsiteAddress],
        ];
    }

    /**
     * @return list<mixed>
     */
    public static function unifiedNumberRules(?int $entityId): array
    {
        return ['required', 'string', 'regex:'.UnifiedNumber::PATTERN, Rule::unique(Entity::class, 'unified_number')->ignore($entityId)];
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    public static function profile(array $validated): EntityProfileData
    {
        $name = is_array($validated['name'] ?? null) ? $validated['name'] : [];
        $grade = $validated['governance_grade'] ?? null;

        return new EntityProfileData(
            EntityName::fromArray($name),
            EntityType::from(ScalarValue::string($validated['entity_type'] ?? null)),
            DevelopmentSector::from(ScalarValue::string($validated['development_sector'] ?? null)),
            OrgSize::from(ScalarValue::string($validated['org_size'] ?? null)),
            ScalarValue::int($validated['founding_year'] ?? null),
            ScalarValue::int($validated['region_id'] ?? null),
            ScalarValue::int($validated['city_id'] ?? null),
            is_string($grade) || is_int($grade) || is_float($grade) ? GovernanceGrade::of($grade)->toDecimal() : null,
            self::optionalCount($validated, 'employees_count'),
            self::optionalCount($validated, 'annual_beneficiaries_count'),
            filter_var($validated['has_strategic_plan'] ?? false, FILTER_VALIDATE_BOOLEAN),
            self::registry($validated),
        );
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private static function registry(array $validated): EntityRegistryUpdate
    {
        $license = $validated['license_number'] ?? null;
        $foundedOn = $validated['founded_on'] ?? null;
        $website = $validated['website_url'] ?? null;
        $unifiedNumber = $validated['unified_number'] ?? null;

        return new EntityRegistryUpdate(
            is_string($license) ? new LicenseNumber($license) : null,
            is_string($foundedOn) ? CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $foundedOn) ?: null : null,
            is_string($website) ? new WebsiteUrl($website) : null,
            array_values(array_filter(EntityProfileField::registryDetails(), static fn (EntityProfileField $field): bool => array_key_exists($field->value, $validated))),
            is_string($unifiedNumber) ? new UnifiedNumber($unifiedNumber) : null,
        );
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    public static function contact(array $validated): EntityContactData
    {
        $phone = $validated['phone'] ?? null;

        return new EntityContactData(
            trim(ScalarValue::string($validated['authorized_person_name'] ?? null)),
            is_string($phone) && $phone !== '' ? new PhoneNumber(self::toInternational($phone)) : null,
        );
    }

    private static function toInternational(string $phone): string
    {
        return str_starts_with($phone, '05') ? '+966'.substr($phone, 1) : $phone;
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private static function optionalCount(array $validated, string $key): ?int
    {
        $value = $validated[$key] ?? null;

        return $value === null ? null : ScalarValue::int($value);
    }

    private static function cityOfRegion(mixed $regionId): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($regionId): void {
            $belongs = is_numeric($value) && is_numeric($regionId) && City::query()
                ->whereKey((int) $value)
                ->where('region_id', (int) $regionId)
                ->where('is_active', true)
                ->exists();

            if (! $belongs) {
                $fail(__('validation.custom.city_id.not_in_region'));
            }
        };
    }
}
