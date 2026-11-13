<?php

declare(strict_types=1);

namespace App\Support\Periods;

use App\Data\Periods\PeriodicSettingsUpdate;
use App\Enums\Periods\CertificateValidityRule;
use App\Enums\Periods\PeriodCadence;
use App\Enums\Periods\PeriodicAudience;
use App\Support\ScalarValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PeriodicSettingsRules
{
    public const int MAX_REMINDERS = 6;

    public const int MAX_PAID_DRAFT_REMINDER_DAYS = 60;

    /**
     * @return array<string, list<mixed>>
     */
    public static function for(PeriodCadence $cadence): array
    {
        $days = 'between:1,'.$cadence->maxDays();

        return [
            'cadence' => ['sometimes', 'required', Rule::enum(PeriodCadence::class)],
            'notice_days_before' => ['sometimes', 'required', 'integer', $days],
            'reminder_days_before_end' => ['sometimes', 'required', 'array', 'list', 'min:1', 'max:'.self::MAX_REMINDERS],
            'reminder_days_before_end.*' => ['required', 'integer', 'distinct', $days],
            'audience' => ['sometimes', 'required', Rule::enum(PeriodicAudience::class)],
            'certificate_validity' => ['sometimes', 'required', Rule::enum(CertificateValidityRule::class)],
            'paid_draft_reminder_days' => ['sometimes', 'required', 'integer', 'between:1,'.self::MAX_PAID_DRAFT_REMINDER_DAYS],
        ];
    }

    public static function boundingCadence(FormRequest $request, PeriodCadence $stored): PeriodCadence
    {
        $submitted = $request->input('cadence');

        return is_string($submitted) ? (PeriodCadence::tryFrom($submitted) ?? $stored) : $stored;
    }

    public static function updateFrom(FormRequest $request, ?string $reason): PeriodicSettingsUpdate
    {
        return new PeriodicSettingsUpdate(
            $request->enum('cadence', PeriodCadence::class),
            $request->filled('notice_days_before') ? $request->integer('notice_days_before') : null,
            $request->has('reminder_days_before_end') ? self::days($request->input('reminder_days_before_end')) : null,
            $request->enum('audience', PeriodicAudience::class),
            $request->enum('certificate_validity', CertificateValidityRule::class),
            $request->filled('paid_draft_reminder_days') ? $request->integer('paid_draft_reminder_days') : null,
            $reason,
        );
    }

    /**
     * @return list<int>
     */
    private static function days(mixed $values): array
    {
        return array_values(array_map(static fn (mixed $day): int => ScalarValue::int($day), is_array($values) ? $values : []));
    }
}
