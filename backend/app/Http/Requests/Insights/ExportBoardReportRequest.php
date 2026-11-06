<?php

declare(strict_types=1);

namespace App\Http\Requests\Insights;

use App\Enums\Identity\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ExportBoardReportRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['locale' => ['nullable', Rule::enum(Locale::class)]];
    }

    public function documentLocale(): Locale
    {
        return Locale::tryFrom($this->string('locale')->toString()) ?? Locale::tryFrom(app()->getLocale()) ?? Locale::Arabic;
    }
}
