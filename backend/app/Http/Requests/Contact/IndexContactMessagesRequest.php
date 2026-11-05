<?php

declare(strict_types=1);

namespace App\Http\Requests\Contact;

use App\Data\Contact\ContactAssigneeFilter;
use App\Data\Contact\ContactMessageFilters;
use App\Enums\Contact\ContactEnquiryType;
use App\Enums\Contact\ContactMessageStatus;
use App\Rules\SearchableTerm;
use App\Support\ScalarValue;
use App\Support\ValueObjects\SearchTerm;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

final class IndexContactMessagesRequest extends FormRequest
{
    private const string UNASSIGNED = 'none';

    private const string MINE = 'me';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:status,enquiry_type,from,to,linked,assignee'],
            'filter.status' => ['nullable', Rule::enum(ContactMessageStatus::class)],
            'filter.enquiry_type' => ['nullable', Rule::enum(ContactEnquiryType::class)],
            'filter.from' => ['nullable', 'date_format:Y-m-d'],
            'filter.to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:filter.from'],
            'filter.linked' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
            'filter.assignee' => ['nullable', 'string', 'regex:/^(none|me|[1-9][0-9]{0,9})$/'],
            'search' => ['nullable', 'string', 'max:100', new SearchableTerm(2)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): ContactMessageFilters
    {
        $status = $this->input('filter.status');
        $type = $this->input('filter.enquiry_type');
        $search = $this->input('search');

        return new ContactMessageFilters(
            is_string($status) ? ContactMessageStatus::tryFrom($status) : null,
            is_string($type) ? ContactEnquiryType::tryFrom($type) : null,
            $this->dayBoundary('filter.from', startOfDay: true),
            $this->dayBoundary('filter.to', startOfDay: false),
            $this->filled('filter.linked') ? filter_var($this->input('filter.linked'), FILTER_VALIDATE_BOOLEAN) : null,
            $this->assignee(),
            is_string($search) ? SearchTerm::tryFrom($search) : null,
            $this->integer('per_page', 15),
        );
    }

    private function dayBoundary(string $key, bool $startOfDay): ?CarbonInterface
    {
        $value = $this->input($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        $day = Date::createFromFormat('Y-m-d', $value, config()->string('app.display_timezone'));

        if (! $day instanceof CarbonInterface) {
            return null;
        }

        return ($startOfDay ? $day->startOfDay() : $day->endOfDay())->utc();
    }

    private function assignee(): ?ContactAssigneeFilter
    {
        $value = $this->input('filter.assignee');

        return match (true) {
            ! is_string($value) || $value === '' => null,
            $value === self::UNASSIGNED => ContactAssigneeFilter::unassigned(),
            $value === self::MINE => new ContactAssigneeFilter(ScalarValue::int($this->user()?->getAuthIdentifier())),
            default => new ContactAssigneeFilter((int) $value),
        };
    }
}
