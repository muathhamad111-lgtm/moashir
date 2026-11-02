<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Data\Identity\PreferenceChanges;
use App\Enums\Identity\Locale;
use App\Enums\Identity\ThemePreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePreferencesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', Rule::enum(Locale::class)],
            'theme' => ['sometimes', Rule::enum(ThemePreference::class)],
        ];
    }

    public function changes(): PreferenceChanges
    {
        return new PreferenceChanges($this->enum('locale', Locale::class), $this->enum('theme', ThemePreference::class));
    }
}
