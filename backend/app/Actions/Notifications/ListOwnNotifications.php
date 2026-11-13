<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Data\Notifications\NotificationListFilters;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Notifications\NotificationCategory;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Models\Notifications\Notification;
use App\Support\Search\SearchTextNormalizer;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListOwnNotifications
{
    private const array SEARCHED_TEXTS = ["title->>'ar'", "title->>'en'", "body->>'ar'", "body->>'en'"];

    /**
     * @return LengthAwarePaginator<int, Notification>
     */
    public function handle(User $user, NotificationListFilters $filters): LengthAwarePaginator
    {
        return Notification::query()
            ->ownedBy($user->id)
            ->when($filters->archived, static fn (Builder $query): Builder => $query->whereNotNull('archived_at'), static fn (Builder $query): Builder => $query->inInbox())
            ->when($filters->category instanceof NotificationCategory, static fn (Builder $query): Builder => $query->where('category', $filters->category))
            ->when($filters->code instanceof NotificationCode, static fn (Builder $query): Builder => $query->where('code', $filters->code?->value))
            ->when($filters->path instanceof AssessmentPath, static fn (Builder $query): Builder => $query->where('path', $filters->path))
            ->when($filters->isRead !== null, static fn (Builder $query): Builder => $filters->isRead === true ? $query->whereNotNull('read_at') : $query->unread())
            ->when($filters->search instanceof SearchTerm, fn (Builder $query): Builder => $this->matching($query, $filters->search))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters->perPage)
            ->withQueryString();
    }

    /**
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    private function matching(Builder $query, ?SearchTerm $term): Builder
    {
        $pattern = $term?->containsPattern() ?? '';

        return $query->where(static function (Builder $texts) use ($pattern): void {
            foreach (self::SEARCHED_TEXTS as $text) {
                $texts->orWhereRaw(SearchTextNormalizer::DATABASE_FUNCTION.'('.$text.') like ?', [$pattern]);
            }
        });
    }
}
