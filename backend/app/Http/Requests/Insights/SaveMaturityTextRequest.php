<?php

declare(strict_types=1);

namespace App\Http\Requests\Insights;

use App\Data\Insights\MaturityTextEdit;
use App\Enums\Insights\MaturityBand;
use App\Rules\FreeOfHtmlMarkup;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Foundation\Http\FormRequest;

final class SaveMaturityTextRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maximum = config()->integer('moashir.insights.maturity_text_max_chars');

        return [
            'main_criterion_code' => ['nullable', 'string', 'max:20', 'exists:main_criteria,code'],
            'text' => ['required', 'array:ar,en'],
            'text.ar' => ['required', 'string', 'max:'.$maximum, new FreeOfHtmlMarkup],
            'text.en' => ['required', 'string', 'max:'.$maximum, new FreeOfHtmlMarkup],
        ];
    }

    public function edit(MaturityBand $band): MaturityTextEdit
    {
        return new MaturityTextEdit(
            $band,
            $this->filled('main_criterion_code') ? $this->string('main_criterion_code')->toString() : null,
            new LocalizedText($this->string('text.ar')->toString(), $this->string('text.en')->toString()),
        );
    }
}
