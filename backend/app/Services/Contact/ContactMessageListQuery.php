<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\Data\Contact\ContactAssigneeFilter;
use App\Data\Contact\ContactMessageFilters;
use App\Enums\Contact\ContactEnquiryType;
use App\Enums\Contact\ContactMessageStatus;
use App\Models\Contact\ContactMessage;
use App\Support\ValueObjects\SearchTerm;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class ContactMessageListQuery
{
    private const array SEARCHABLE_COLUMNS = ['reference', 'sender_name', 'sender_email', 'organization_name'];

    /**
     * @return Builder<ContactMessage>
     */
    public function filtered(ContactMessageFilters $filters): Builder
    {
        $query = ContactMessage::query();

        if ($filters->status instanceof ContactMessageStatus) {
            $query->where('status', $filters->status);
        }

        if ($filters->enquiryType instanceof ContactEnquiryType) {
            $query->where('enquiry_type', $filters->enquiryType);
        }

        if ($filters->from instanceof CarbonInterface) {
            $query->where('created_at', '>=', $filters->from);
        }

        if ($filters->to instanceof CarbonInterface) {
            $query->where('created_at', '<=', $filters->to);
        }

        $this->applyLinked($query, $filters->isLinked);
        $this->applyAssignee($query, $filters->assignee);
        $this->applySearch($query, $filters->search);

        return $query;
    }

    /**
     * @param  Builder<ContactMessage>  $query
     */
    private function applyLinked(Builder $query, ?bool $isLinked): void
    {
        if ($isLinked === true) {
            $query->whereNotNull('user_id');
        }

        if ($isLinked === false) {
            $query->whereNull('user_id');
        }
    }

    /**
     * @param  Builder<ContactMessage>  $query
     */
    private function applyAssignee(Builder $query, ?ContactAssigneeFilter $assignee): void
    {
        if (! $assignee instanceof ContactAssigneeFilter) {
            return;
        }

        if ($assignee->isUnassigned()) {
            $query->whereNull('assigned_to_user_id');

            return;
        }

        $query->where('assigned_to_user_id', $assignee->userId);
    }

    /**
     * @param  Builder<ContactMessage>  $query
     */
    private function applySearch(Builder $query, ?SearchTerm $search): void
    {
        if (! $search instanceof SearchTerm) {
            return;
        }

        $pattern = $search->containsPattern();

        $query->where(static function (Builder $matches) use ($pattern): void {
            foreach (self::SEARCHABLE_COLUMNS as $column) {
                $matches->orWhereRaw("moashir_search_normalize(coalesce({$column}, '')) like ?", [$pattern]);
            }
        });
    }
}
