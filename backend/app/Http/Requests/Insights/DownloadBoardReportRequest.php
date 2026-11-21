<?php

declare(strict_types=1);

namespace App\Http\Requests\Insights;

use App\Enums\Identity\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DownloadBoardReportRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user' => ['required', 'integer', 'min:1'],
            'locale' => ['required', Rule::enum(Locale::class)],
        ];
    }

    public function boundUserId(): int
    {
        return $this->integer('user');
    }

    public function documentLocale(): Locale
    {
        return Locale::from($this->string('locale')->toString());
    }
}
