<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCvEntry;
use App\Models\Identity\User;

final class CvEntryWriter
{
    /**
     * @param  array<string, string|null>  $bodiesByCode
     * @return list<string>
     */
    public function write(Assessment $locked, array $bodiesByCode, User $editor): array
    {
        $current = AssessmentCvEntry::query()->where('assessment_id', $locked->id)->whereIn('sub_code', array_keys($bodiesByCode))->get()->keyBy('sub_code');
        $changed = [];

        foreach ($bodiesByCode as $code => $body) {
            $entry = $current->get($code);
            $body = $body === null ? '' : trim($body);

            if ($entry instanceof AssessmentCvEntry && $entry->body === $body) {
                continue;
            }

            if (! $entry instanceof AssessmentCvEntry && $body === '') {
                continue;
            }

            $this->apply($locked, $entry, $code, $body, $editor);
            $changed[] = $code;
        }

        return $changed;
    }

    /**
     * @return array<string, string>
     */
    public function bodiesOf(Assessment $assessment): array
    {
        $bodies = [];

        foreach (AssessmentCvEntry::query()->where('assessment_id', $assessment->id)->get(['sub_code', 'body']) as $entry) {
            $bodies[$entry->sub_code] = $entry->body;
        }

        return $bodies;
    }

    private function apply(Assessment $locked, ?AssessmentCvEntry $entry, string $code, string $body, User $editor): void
    {
        if ($body === '') {
            $entry?->delete();

            return;
        }

        AssessmentCvEntry::query()->updateOrCreate(
            ['assessment_id' => $locked->id, 'sub_code' => $code],
            ['body' => $body, 'last_edited_by_user_id' => $editor->id],
        );
    }
}
