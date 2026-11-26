<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\ReviewNotesView;
use App\Models\Assessments\AssessmentReviewNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ReviewNotesView $resource
 */
final class ReviewNotesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = $this->resource;
        $notes = $view->notes;

        return [
            'can_write_review_notes' => $view->canWrite,
            ...($notes === null ? [] : ['review_notes' => array_map(fn (AssessmentReviewNote $note): array => $this->noteOf($note, $view), $notes)]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noteOf(AssessmentReviewNote $note, ReviewNotesView $view): array
    {
        $isMine = $note->isAuthoredBy($view->viewerId);

        return [
            'id' => $note->id,
            'level' => $note->level->value,
            'main_criterion_id' => $note->main_criterion_id,
            'sub_criterion_id' => $note->sub_criterion_id,
            'guiding_point_id' => $note->guiding_point_id,
            'body' => $note->body,
            'author' => [
                'id' => $note->author_user_id,
                'name' => $this->authorNameOf($note, $view, $isMine),
                'role' => $note->author_role->value,
            ],
            'is_mine' => $isMine,
            'updated_at' => $note->created_at->toIso8601String(),
        ];
    }

    private function authorNameOf(AssessmentReviewNote $note, ReviewNotesView $view, bool $isMine): ?string
    {
        if ($isMine) {
            return $note->author->name;
        }

        return $view->revealsAuthors && ! $note->author->isSuperAdmin() ? $note->author->name : null;
    }
}
