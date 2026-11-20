<?php

declare(strict_types=1);

namespace App\Http\Requests\Files;

use App\Data\Files\DownloadClaim;
use App\Enums\Files\AccessIntent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DownloadStoredFileRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user' => ['required', 'integer', 'min:1'],
            'intent' => ['nullable', Rule::enum(AccessIntent::class)],
        ];
    }

    public function claim(): DownloadClaim
    {
        return new DownloadClaim($this->integer('user'), $this->enum('intent', AccessIntent::class) ?? AccessIntent::Download);
    }
}
