<?php

declare(strict_types=1);

namespace App\Data\Contact;

use App\Enums\Contact\ContactEnquiryType;
use App\Enums\Contact\ContactMessageStatus;
use App\Support\ValueObjects\SearchTerm;
use Carbon\CarbonInterface;

final readonly class ContactMessageFilters
{
    public function __construct(
        public ?ContactMessageStatus $status,
        public ?ContactEnquiryType $enquiryType,
        public ?CarbonInterface $from,
        public ?CarbonInterface $to,
        public ?bool $isLinked,
        public ?ContactAssigneeFilter $assignee,
        public ?SearchTerm $search,
        public int $perPage,
    ) {}
}
