<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Settings\SettingKey;
use App\Services\Assessments\AccreditedReviewSla;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\Date;

it('derives the review due date from the submission date and the default SLA of 14 days', function (): void {
    $sla = app(AccreditedReviewSla::class);

    expect($sla->days())->toBe(14)
        ->and($sla->dueAt(Date::parse('2026-11-01T21:30:00+00:00'))->toIso8601String())->toBe('2026-11-15T21:30:00+00:00');
});

it('follows the SLA setting and never goes below one day', function (int $stored, int $expected): void {
    app(SettingsStore::class)->put(SettingKey::AccreditedReviewSlaDays, $stored, AuditEvent::AssessmentSettingsUpdated, null);

    expect(app(AccreditedReviewSla::class)->days())->toBe($expected);
})->with([
    'ten days' => [10, 10],
    'zero falls back to one day' => [0, 1],
]);
