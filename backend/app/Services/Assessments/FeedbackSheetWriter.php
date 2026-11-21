<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\RevisionStamp;
use App\Enums\Assessments\FeedbackKind;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentFeedbackItem;
use App\Models\Identity\User;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\Date;

final readonly class FeedbackSheetWriter
{
    public function __construct(private FeedbackSheetReader $reader, private RevisionStamper $stamper) {}

    /**
     * @param  array<string, array<string, string|null>>  $changesByCode
     * @return array<string, list<string>>
     */
    public function write(Assessment $locked, array $changesByCode, User $author): array
    {
        $current = [];

        foreach ($this->reader->currentRows($locked) as $item) {
            $current[$item->sub_code.'|'.$item->kind->value] = $item;
        }

        $stamp = $this->stamper->stampFor($author, $locked);
        $changed = [];

        foreach ($changesByCode as $code => $fields) {
            foreach ($fields as $kindValue => $body) {
                $kind = FeedbackKind::from($kindValue);

                if ($this->apply($locked, $current[$code.'|'.$kind->value] ?? null, $code, $kind, trim((string) $body), $stamp)) {
                    $changed[$code][] = $kind->field();
                }
            }
        }

        return $changed;
    }

    private function apply(Assessment $locked, ?AssessmentFeedbackItem $current, string $code, FeedbackKind $kind, string $body, RevisionStamp $stamp): bool
    {
        if (($current->body ?? '') === $body) {
            return false;
        }

        $current?->forceFill(['superseded_at' => Date::now()])->save();

        if ($body !== '') {
            AssessmentFeedbackItem::query()->create([
                'assessment_id' => $locked->id,
                'sub_code' => $code,
                'kind' => $kind,
                'body' => $body,
                'author_user_id' => $stamp->authorId,
                'version' => $this->nextVersion($locked, $code, $kind),
                'change_reason' => $stamp->reason,
                'completion_request_id' => $stamp->completionRequestId,
            ]);
        }

        return true;
    }

    private function nextVersion(Assessment $locked, string $code, FeedbackKind $kind): int
    {
        return ScalarValue::int(AssessmentFeedbackItem::query()
            ->where('assessment_id', $locked->id)
            ->where('sub_code', $code)
            ->where('kind', $kind)
            ->max('version')) + 1;
    }
}
