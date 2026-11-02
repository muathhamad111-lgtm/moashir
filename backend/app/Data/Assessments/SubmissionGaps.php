<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class SubmissionGaps
{
    /**
     * @param  list<BoundSub>  $missingCv
     * @param  list<MissingAttachment>  $missingAttachments
     */
    public function __construct(public array $missingCv, public array $missingAttachments) {}

    public function isEmpty(): bool
    {
        return $this->missingCv === [] && $this->missingAttachments === [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        $fields = [];

        foreach ($this->missingCv as $sub) {
            $fields['cv.'.$sub->id] = [__('errors.submission.cv_missing', ['code' => $sub->code])];
        }

        foreach ($this->missingAttachments as $missing) {
            $fields['attachments.'.$missing->subId.'.'.$missing->key] = [__('errors.submission.attachment_missing', ['code' => $missing->subCode])];
        }

        return $fields;
    }

    /**
     * @return array{missing_cv: list<array{sub_criterion_id: int, code: string}>, missing_attachments: list<array{sub_criterion_id: int, code: string, attachment_key: string}>}
     */
    public function toArray(): array
    {
        return [
            'missing_cv' => array_map(static fn (BoundSub $sub): array => ['sub_criterion_id' => $sub->id, 'code' => $sub->code], $this->missingCv),
            'missing_attachments' => array_map(static fn (MissingAttachment $missing): array => $missing->toArray(), $this->missingAttachments),
        ];
    }
}
