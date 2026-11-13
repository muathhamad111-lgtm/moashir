<?php

declare(strict_types=1);

namespace Database\Factories\Contact;

use App\Enums\Contact\ContactEnquiryType;
use App\Enums\Contact\ContactMessageStatus;
use App\Enums\Identity\Locale;
use App\Models\Contact\ContactMessage;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<ContactMessage>
 */
final class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sequence = fake()->unique()->numberBetween(1, 999_999);

        return [
            'reference' => sprintf('CNT-%d-%06d', Date::now()->year, $sequence),
            'status' => ContactMessageStatus::New,
            'enquiry_type' => ContactEnquiryType::General,
            'sender_name' => 'سارة العبدالله',
            'sender_email' => 'sender'.$sequence.'@example.sa',
            'sender_mobile' => null,
            'organization_name' => 'جمعية الأمل الخيرية',
            'locale' => Locale::Arabic,
            'privacy_consent_at' => Date::now(),
            'last_activity_at' => Date::now(),
        ];
    }

    public function closedAt(DateTimeInterface $closedAt): self
    {
        return $this->state(['status' => ContactMessageStatus::Closed, 'closed_at' => $closedAt, 'last_activity_at' => $closedAt]);
    }
}
