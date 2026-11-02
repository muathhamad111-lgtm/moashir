<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Data\Award\CommitteeNoteChanges;
use Illuminate\Foundation\Http\FormRequest;

final class SaveCommitteeNotesRequest extends FormRequest
{
    private const int MAX_CHARS = 5000;

    private const int MAX_ITEMS = 500;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['required_without:general_note', 'array', 'max:'.self::MAX_ITEMS],
            'notes.*' => ['nullable', 'string', 'max:'.self::MAX_CHARS],
            'general_note' => ['nullable', 'string', 'max:'.self::MAX_CHARS],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function changes(): CommitteeNoteChanges
    {
        $subs = [];
        $notes = $this->validated('notes');

        foreach (is_array($notes) ? $notes : [] as $subId => $body) {
            $subs[(int) $subId] = $this->clean($body);
        }

        return new CommitteeNoteChanges($subs, $this->exists('general_note'), $this->clean($this->input('general_note')));
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }

    private function clean(mixed $body): ?string
    {
        $text = is_string($body) ? trim($body) : '';

        return $text === '' ? null : $text;
    }
}
