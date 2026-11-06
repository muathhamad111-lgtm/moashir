<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

final class ReorderCmsContentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ordered_ids' => ['required', 'array', 'min:1', 'max:200'],
            'ordered_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function orderedIds(): array
    {
        $ids = $this->input('ordered_ids');

        return is_array($ids) ? array_values(array_map(intval(...), array_filter($ids, is_numeric(...)))) : [];
    }
}
