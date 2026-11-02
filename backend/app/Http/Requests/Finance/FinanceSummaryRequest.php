<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class FinanceSummaryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function from(): ?CarbonImmutable
    {
        return $this->dateOf('from');
    }

    public function to(): ?CarbonImmutable
    {
        return $this->dateOf('to');
    }

    private function dateOf(string $key): ?CarbonImmutable
    {
        return $this->filled($key) ? CarbonImmutable::createFromFormat('Y-m-d', $this->string($key)->toString())?->startOfDay() : null;
    }
}
