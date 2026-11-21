<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class SubmissionGaps
{
    /**
     * @param  list<BoundSub>  $missingCv
     * @param  list<MissingAttachment>  $missingAttachments
     * @param  list<MissingMainAttachment>  $missingMainAttachments
     */
    public function __construct(public array $missingCv, public array $missingAttachments, public array $missingMainAttachments = []) {}

    public function isEmpty(): bool
    {
        return $this->missingCv === [] && $this->missingAttachments === [] && $this->missingMainAttachments === [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        $fields = [];

        foreach ($this->missingCv as $sub) {
            $fields['cv.'.$sub->id] = [__('errors.submission.cv_missing')];
        }

        foreach ($this->missingAttachments as $missing) {
            $fields['attachments.'.$missing->subId.'.'.$missing->key] = [__('errors.submission.attachment_missing')];
        }

        foreach ($this->missingMainAttachments as $missing) {
            $fields['main_attachments.'.$missing->mainId.'.'.$missing->key] = [__('errors.submission.attachment_missing')];
        }

        return $fields;
    }

    /**
     * @return array{missing_cv: list<array{sub_criterion_id: int, code: string}>, missing_attachments: list<array{sub_criterion_id: int, code: string, attachment_key: string}>, missing_main_attachments: list<array{main_criterion_id: int, code: string, attachment_key: string}>}
     */
    public function toArray(): array
    {
        return [
            'missing_cv' => array_map(static fn (BoundSub $sub): array => ['sub_criterion_id' => $sub->id, 'code' => $sub->code], $this->missingCv),
            'missing_attachments' => array_map(static fn (MissingAttachment $missing): array => $missing->toArray(), $this->missingAttachments),
            'missing_main_attachments' => array_map(static fn (MissingMainAttachment $missing): array => $missing->toArray(), $this->missingMainAttachments),
        ];
    }
}
