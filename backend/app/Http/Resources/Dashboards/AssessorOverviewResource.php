<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboards;

use App\Data\Dashboards\AssessorOverview;
use App\Data\Dashboards\AssessorPeriodFigures;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Dashboards\CardFormat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AssessorOverview $resource
 */
final class AssessorOverviewResource extends JsonResource
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
            'previous_period' => [...$this->window($overview->period->previous()), 'basis' => $overview->period->comparisonBasis()->value],
            'cards' => $this->cards($overview->current, $overview->previous),
            'trends' => [
                'bucket' => $overview->trends->bucket->value,
                'buckets' => $overview->trends->buckets,
                'series' => $overview->trends->series,
            ],
            'paths' => array_map(
                static fn (string $path, int $completed): array => ['path' => $path, 'completed' => $completed],
                array_keys($overview->current->completedByPath),
                array_values($overview->current->completedByPath),
            ),
            'levels' => array_map(
                static fn (string $level, int $count): array => ['key' => $level, 'count' => $count],
                array_keys($overview->levels),
                array_values($overview->levels),
            ),
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
    private function cards(AssessorPeriodFigures $current, AssessorPeriodFigures $previous): array
    {
        return [
            $this->card('completed', $current->completed, $previous->completed),
            $this->card('average_duration_days', $current->averageDays, $previous->averageDays, CardFormat::Days),
            $this->card('average_result_percent', $current->averagePercent, $previous->averagePercent, CardFormat::Percent),
            $this->card('completion_requests', $current->completionRequests, $previous->completionRequests),
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
