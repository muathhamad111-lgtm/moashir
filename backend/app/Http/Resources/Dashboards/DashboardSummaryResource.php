<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboards;

use App\Data\Dashboards\DashboardCard;
use App\Data\Dashboards\DashboardSummary;
use App\Data\Dashboards\DashboardWidget;
use App\Enums\Dashboards\DashboardBoard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read DashboardSummary $resource
 */
final class DashboardSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = $this->resource;

        return [
            'board' => $summary->board->value,
            'landing_board' => $summary->heldBoards->landing->value,
            'available_boards' => array_map(static fn (DashboardBoard $board): string => $board->value, $summary->heldBoards->boards),
            'cards' => array_map(static fn (DashboardCard $card): array => [
                'key' => $card->key,
                'value' => $card->value,
                'format' => $card->format->value,
                'link' => $card->link,
            ], $summary->content->cards),
            'widgets' => array_map(static fn (DashboardWidget $widget): array => [
                'key' => $widget->key,
                'data' => $widget->data,
            ], $summary->content->widgets),
            'action_center' => ActionItemResource::collection($summary->actionCenter)->resolve($request),
            'generated_at' => $summary->generatedAt->toIso8601String(),
        ];
    }
}
