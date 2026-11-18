<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Data\Criteria\WeightAssignment;
use App\Support\ScalarValue;
use App\Support\ValueObjects\Weight;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateChildWeightsRequest extends FormRequest
{
    private const string MAX_WEIGHT = '100000';

    private const int MAX_ITEMS = 200;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'weights' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'weights.*' => ['array:id,weight'],
            'weights.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'weights.*.weight' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_WEIGHT],
        ];
    }

    /**
     * @return list<WeightAssignment>
     */
    public function assignments(): array
    {
        $items = $this->validated('weights');
        $assignments = [];

        foreach (is_array($items) ? $items : [] as $item) {
            if (is_array($item)) {
                $assignments[] = new WeightAssignment(ScalarValue::int($item['id'] ?? null), Weight::of(ScalarValue::string($item['weight'] ?? null)));
            }
        }

        return $assignments;
    }
}
