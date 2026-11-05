<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Data\Notifications\NotificationListFilters;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Notifications\NotificationCategory;
use App\Enums\Notifications\NotificationCode;
use App\Rules\SearchableTerm;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListNotificationsRequest extends FormRequest
{
    private const int DEFAULT_PER_PAGE = 15;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100', new SearchableTerm(2)],
            'filter' => ['nullable', 'array:category,read,archived,code,path'],
            'filter.category' => ['nullable', Rule::enum(NotificationCategory::class)],
            'filter.read' => ['nullable', 'in:true,false'],
            'filter.archived' => ['nullable', 'in:true,false'],
            'filter.code' => ['nullable', Rule::enum(NotificationCode::class)],
            'filter.path' => ['nullable', Rule::enum(AssessmentPath::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): NotificationListFilters
    {
        return new NotificationListFilters(
            $this->enum('filter.category', NotificationCategory::class),
            $this->flag('filter.read'),
            $this->flag('filter.archived') === true,
            $this->enum('filter.code', NotificationCode::class),
            $this->filled('search') ? SearchTerm::tryFrom($this->string('search')->toString()) : null,
            $this->integer('per_page', self::DEFAULT_PER_PAGE),
            $this->enum('filter.path', AssessmentPath::class),
        );
    }

    private function flag(string $key): ?bool
    {
        return $this->filled($key) ? $this->string($key)->toString() === 'true' : null;
    }
}
