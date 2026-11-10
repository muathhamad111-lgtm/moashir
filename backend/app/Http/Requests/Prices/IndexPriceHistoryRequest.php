<?php

declare(strict_types=1);

namespace App\Http\Requests\Prices;

use App\Enums\Finance\PaymentPurpose;
use Illuminate\Foundation\Http\FormRequest;

final class IndexPriceHistoryRequest extends FormRequest
{
    private const int DEFAULT_PER_PAGE = 20;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function purpose(): PaymentPurpose
    {
        $key = $this->route('key');

        return PaymentPurpose::from(is_string($key) ? $key : '');
    }

    public function perPage(): int
    {
        return $this->integer('per_page', self::DEFAULT_PER_PAGE);
    }
}
