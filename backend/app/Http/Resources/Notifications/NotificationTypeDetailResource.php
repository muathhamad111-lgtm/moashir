<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\Data\Notifications\NotificationChannelState;
use App\Data\Notifications\NotificationTextView;
use App\Data\Notifications\NotificationTypeTexts;
use App\Enums\Notifications\NotificationChannel;
use App\Models\Notifications\NotificationTextOverride;
use App\Services\Notifications\SmsTextApprovals;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read NotificationTypeTexts $resource
 */
final class NotificationTypeDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->resource->type;

        return [
            'code' => $type->code->value,
            'category' => $type->code->category()->value,
            'is_mandatory' => $type->isMandatory,
            'channels' => array_map(static fn (NotificationChannel $channel): string => $channel->value, $type->channels),
            'channel_settings' => array_map(static fn (NotificationChannelState $state): array => [
                'channel' => $state->channel->value,
                'is_enabled' => $state->isEnabled,
                'is_shipped' => $state->isShipped,
                'is_switchable' => $state->isSwitchable,
            ], $this->resource->channels),
            'texts' => array_map($this->text(...), $this->resource->texts),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function text(NotificationTextView $view): array
    {
        $parts = [];

        foreach ($view->template->parts as $part => $default) {
            $parts[$part] = ['default' => $default, 'current' => $view->current($part)];
        }

        return [
            'key' => $view->template->key,
            'variant' => $view->template->variant(),
            'variables' => $view->template->placeholders,
            'required_variables' => $view->requiredVariables,
            'is_customized' => $view->override instanceof NotificationTextOverride,
            'updated_at' => $view->override?->updated_at->toIso8601String(),
            'parts' => $parts,
            'sms' => $this->sms($view),
        ];
    }

    /**
     * @return array{current: array{ar: string, en: string}|null, approval: array{ar: string, en: string}|null}
     */
    private function sms(NotificationTextView $view): array
    {
        $text = $view->sms();
        $approvals = app(SmsTextApprovals::class);

        return [
            'current' => $text,
            'approval' => $text === null ? null : ['ar' => $approvals->of($text['ar'])->value, 'en' => $approvals->of($text['en'])->value],
        ];
    }
}
