<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Data\Audit\AuditLogFilters;
use App\Support\ScalarValue;
use Carbon\CarbonImmutable;

final class AuditLogFilterInput
{
    public const int MAX_PER_PAGE = 100;

    private const string DATE_FORMAT = 'Y-m-d';

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:from,to,actor_id,event,subject_type,subject_id,ip'],
            'filter.from' => ['nullable', 'date_format:'.self::DATE_FORMAT],
            'filter.to' => ['nullable', 'date_format:'.self::DATE_FORMAT, 'after_or_equal:filter.from'],
            'filter.actor_id' => ['nullable', 'integer', 'min:1'],
            'filter.event' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_.]+$/'],
            'filter.subject_type' => ['nullable', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/'],
            'filter.subject_id' => ['nullable', 'integer', 'min:1'],
            'filter.ip' => ['nullable', 'ip'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    public static function filters(array $validated): AuditLogFilters
    {
        $filter = is_array($validated['filter'] ?? null) ? $validated['filter'] : [];

        return new AuditLogFilters(
            self::day($filter['from'] ?? null)?->startOfDay()->utc(),
            self::day($filter['to'] ?? null)?->endOfDay()->utc(),
            self::optionalInt($filter['actor_id'] ?? null),
            self::optionalString($filter['event'] ?? null),
            self::optionalString($filter['subject_type'] ?? null),
            self::optionalInt($filter['subject_id'] ?? null),
            self::optionalString($filter['ip'] ?? null),
            ScalarValue::int($validated['per_page'] ?? null, 25),
        );
    }

    private static function day(mixed $value): ?CarbonImmutable
    {
        return is_string($value) ? (CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $value, config()->string('app.display_timezone')) ?: null) : null;
    }

    private static function optionalInt(mixed $value): ?int
    {
        return $value === null ? null : ScalarValue::int($value);
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
