<?php

declare(strict_types=1);

namespace App\Http\Requests\Files;

use Illuminate\Foundation\Http\FormRequest;

final class DownloadStoredFileRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['user' => ['required', 'integer', 'min:1']];
    }

    public function boundUserId(): int
    {
        return $this->integer('user');
    }
}
