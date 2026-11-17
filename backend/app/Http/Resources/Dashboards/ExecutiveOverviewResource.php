<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboards;

use App\Data\Dashboards\DashboardWidget;
use App\Data\Dashboards\ExecutiveOverview;
use App\Data\Dashboards\PeriodFigures;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Dashboards\CardFormat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ExecutiveOverview $resource
 */
final class ExecutiveOverviewResource extends JsonResource
{
    private const string DAY_FORMAT = 'Y-m-d';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $overview = $this->resource;

        return [
            'period' => [...$this->window($overview->period), 'preset' => $overview->period->preset->value],
            'previous_period' => $this->window($overview->period->previous()),
            'cards' => $this->cards($overview->current, $overview->previous, $overview->activeEntities),
            'trends' => [
                'bucket' => $overview->trends->bucket->value,
                'buckets' => $overview->trends->buckets,
                'series' => $overview->trends->series,
            ],
            'paths' => array_map(
                static fn (string $path, array $counts): array => ['path' => $path, ...$counts],
                array_keys($overview->current->paths),
                array_values($overview->current->paths),
            ),
            'snapshot' => array_map(static fn (DashboardWidget $widget): array => [
                'key' => $widget->key,
                'data' => $widget->data,
            ], $overview->snapshot),
            'generated_at' => $overview->generatedAt->toIso8601String(),
        ];
    }

    /**
     * @return array{from: string, to: string}
     */
    private function window(ReportingPeriod $period): array
    {
        return ['from' => $period->start->format(self::DAY_FORMAT), 'to' => $period->lastDay()->format(self::DAY_FORMAT)];
    }

    /**
     * @return list<array{key: string, value: int|string|null, previous: int|string|null, format: string}>
     */
    private function cards(PeriodFigures $current, PeriodFigures $previous, int $activeEntities): array
    {
        return [
            $this->card('new_entities', $current->newEntities, $previous->newEntities),
            $this->card('requests_submitted', $current->requestsSubmitted, $previous->requestsSubmitted),
            $this->card('assessments_completed', $current->assessmentsCompleted, $previous->assessmentsCompleted),
            $this->card('accredited_average_percent', $current->accreditedAveragePercent, $previous->accreditedAveragePercent, CardFormat::Percent),
            $this->card('payments_collected', $current->paymentsCollected, $previous->paymentsCollected, CardFormat::Money),
            $this->card('entities_active', $activeEntities, null),
        ];
    }

    /**
     * @return array{key: string, value: int|string|null, previous: int|string|null, format: string}
     */
    private function card(string $key, int|string|null $value, int|string|null $previous, CardFormat $format = CardFormat::Count): array
    {
        return ['key' => $key, 'value' => $value, 'previous' => $previous, 'format' => $format->value];
    }
}
