<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Enums\Notifications\NotificationCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MarkAllNotificationsReadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['nullable', Rule::enum(NotificationCategory::class)],
        ];
    }

    public function category(): ?NotificationCategory
    {
        return $this->enum('category', NotificationCategory::class);
    }
}
