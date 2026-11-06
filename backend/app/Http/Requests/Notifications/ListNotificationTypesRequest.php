<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Data\Notifications\NotificationTypeFilters;
use App\Enums\Notifications\NotificationAudience;
use App\Enums\Notifications\NotificationCategory;
use App\Enums\Notifications\NotificationCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListNotificationTypesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:module,category,audience'],
            'filter.module' => ['nullable', Rule::in($this->modules())],
            'filter.category' => ['nullable', Rule::enum(NotificationCategory::class)],
            'filter.audience' => ['nullable', Rule::enum(NotificationAudience::class)],
        ];
    }

    public function filters(): NotificationTypeFilters
    {
        return new NotificationTypeFilters(
            $this->filled('filter.module') ? $this->string('filter.module')->toString() : null,
            $this->enum('filter.category', NotificationCategory::class),
            $this->enum('filter.audience', NotificationAudience::class),
        );
    }

    /**
     * @return list<string>
     */
    private function modules(): array
    {
        return array_values(array_unique(array_map(static fn (NotificationCode $code): string => $code->module(), NotificationCode::cases())));
    }
}
