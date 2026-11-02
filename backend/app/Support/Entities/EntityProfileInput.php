<?php

declare(strict_types=1);

namespace App\Support\Entities;

use App\Data\Entities\EntityContactData;
use App\Data\Entities\EntityProfileData;
use App\Enums\Entities\DevelopmentSector;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Models\Entities\City;
use App\Models\Entities\Region;
use App\Support\ScalarValue;
use App\Support\ValueObjects\EntityName;
use App\Support\ValueObjects\GovernanceGrade;
use App\Support\ValueObjects\PhoneNumber;
use Closure;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

final class EntityProfileInput
{
    private const string PHONE_PATTERN = '/^(05\d{8}|\+9665\d{8})$/';

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
