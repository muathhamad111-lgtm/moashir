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
            ...$this->tableColumnRules(),
        ];
    }

    public function changes(): PreferenceChanges
    {
        /** @var array<string, list<string>|null> $tableColumns */
        $tableColumns = $this->validated('table_columns', []);

        return new PreferenceChanges($this->enum('locale', Locale::class), $this->enum('theme', ThemePreference::class), $tableColumns);
    }

    /**
     * Only the tables the platform lists, each with only the columns it offers (null restores the defaults).
     *
     * @return array<string, mixed>
     */
    private function tableColumnRules(): array
    {
        $tables = config()->array('table_columns.tables');
        $rules = ['table_columns' => ['sometimes', 'array:'.implode(',', array_keys($tables)), 'max:'.config()->integer('table_columns.max_tables')]];

        foreach ($tables as $table => $columns) {
            if (! is_array($columns)) {
                continue;
            }

            $rules["table_columns.{$table}"] = ['sometimes', 'nullable', 'array', 'max:'.count($columns)];
            $rules["table_columns.{$table}.*"] = ['string', 'distinct', Rule::in($columns)];
        }

        return $rules;
    }
}
