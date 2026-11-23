<?php

declare(strict_types=1);

namespace App\Http\Requests\Nda;

use App\Data\Nda\NdaPublication;
use App\Rules\FreeOfHtmlMarkup;
use App\Support\RichText\RichTextSanitizer;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Foundation\Http\FormRequest;

final class PublishNdaRequest extends FormRequest
{
    private const int SUMMARY_MAX = 2000;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'change_summary' => ['required', 'array:ar,en'],
            'change_summary.ar' => ['required', 'string', 'max:'.self::SUMMARY_MAX, new FreeOfHtmlMarkup],
            'change_summary.en' => ['required', 'string', 'max:'.self::SUMMARY_MAX, new FreeOfHtmlMarkup],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm_irreversible' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'change_summary' => __('nda.attributes.change_summary'),
            'change_summary.ar' => __('nda.attributes.change_summary_ar'),
            'change_summary.en' => __('nda.attributes.change_summary_en'),
            'reason' => __('nda.attributes.reason'),
            'confirm_irreversible' => __('nda.attributes.confirm_irreversible'),
        ];
    }

    public function publication(): NdaPublication
    {
        return new NdaPublication(
            new LocalizedText($this->string('change_summary.ar')->toString(), $this->string('change_summary.en')->toString()),
            $this->string('reason')->trim()->toString(),
        );
    }

    protected function prepareForValidation(): void
    {
        $summary = $this->input('change_summary');

        if (! is_array($summary)) {
            return;
        }

        foreach (['ar', 'en'] as $locale) {
            if (is_string($summary[$locale] ?? null)) {
                $summary[$locale] = RichTextSanitizer::clean($summary[$locale]);
            }
        }

        $this->merge(['change_summary' => $summary]);
    }
}
