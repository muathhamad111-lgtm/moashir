<?php

declare(strict_types=1);

namespace App\Http\Requests\Prices;

use App\Data\Prices\PriceChange;
use App\Enums\Assessments\PathAvailability;
use App\Enums\Finance\PaymentPurpose;
use App\Rules\PriceOrFreeBeforeVat;
use App\Services\Prices\PriceEditWindowReader;
use App\Support\ValueObjects\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePathPriceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'price_before_vat' => ['required', new PriceOrFreeBeforeVat],
            'availability' => ['required', 'string', Rule::enum(PathAvailability::class)],
            'effective_from' => ['nullable', 'string', 'date_format:Y-m-d', $this->withinWindow(...)],
            'base_version_id' => ['present', 'nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function purpose(): PaymentPurpose
    {
        $key = $this->route('key');

        return PaymentPurpose::from(is_string($key) ? $key : '');
    }

    public function toChange(): PriceChange
    {
        $window = app(PriceEditWindowReader::class)->current();

        return new PriceChange(
            new Money($this->string('price_before_vat')->toString()),
            PathAvailability::from($this->string('availability')->toString()),
            $this->filled('effective_from') ? $window->startOf($this->string('effective_from')->toString())->utc() : null,
            $this->filled('base_version_id') ? $this->integer('base_version_id') : null,
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }

    private function withinWindow(string $attribute, mixed $value, Closure $fail): void
    {
        $window = app(PriceEditWindowReader::class)->current();

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return;
        }

        if (! $window->contains($window->startOf($value))) {
            $fail(__('validation.custom.effective_from.window', [
                'min' => $window->firstDate->toDateString(),
                'max' => $window->lastDate->toDateString(),
            ]));
        }
    }
}
