<?php

declare(strict_types=1);

namespace App\Http\Requests\Contact;

use App\Data\Contact\ContactIdentity;
use App\Data\Contact\ContactSubmission;
use App\Enums\Contact\ContactEnquiryType;
use App\Enums\Identity\Locale;
use App\Models\Identity\User;
use App\Services\Contact\SignedInSender;
use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SubmitContactMessageRequest extends FormRequest
{
    public const string HONEYPOT = 'website';

    private const string MOBILE_PATTERN = '/^(05\d{8}|\+9665\d{8})$/';

    private ?User $signedInSender = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(SignedInSender $senders): array
    {
        $this->signedInSender = $senders->of($this);
        $identity = $this->signedInSender instanceof User ? 'nullable' : 'required';

        return [
            'name' => [$identity, 'string', 'min:2', 'max:150'],
            'email' => [$identity, 'string', 'email:filter', 'max:254'],
            'mobile' => ['nullable', 'string', 'regex:'.self::MOBILE_PATTERN],
            'organization' => ['nullable', 'string', 'max:200'],
            'enquiry_type' => ['required', Rule::enum(ContactEnquiryType::class)],
            'message' => ['required', 'string', 'min:10', 'max:5000', 'not_regex:/\x00/'],
            'privacy_consent' => ['accepted'],
            self::HONEYPOT => ['nullable', 'string', 'max:500'],
        ];
    }

    public function sender(): ?User
    {
        return $this->signedInSender;
    }

    public function submission(): ContactSubmission
    {
        return new ContactSubmission(
            $this->signedInSender instanceof User ? null : $this->guestIdentity(),
            $this->mobile(),
            ContactEnquiryType::from($this->string('enquiry_type')->toString()),
            $this->string('message')->trim()->toString(),
            Locale::tryFrom(app()->getLocale()) ?? Locale::Arabic,
            $this->filled(self::HONEYPOT),
        );
    }

    private function guestIdentity(): ContactIdentity
    {
        $organization = $this->string('organization')->trim()->toString();

        return new ContactIdentity(
            $this->string('name')->trim()->toString(),
            new EmailAddress($this->string('email')->toString()),
            $organization === '' ? null : $organization,
        );
    }

    private function mobile(): ?PhoneNumber
    {
        $mobile = $this->string('mobile')->trim()->toString();

        if ($mobile === '') {
            return null;
        }

        return new PhoneNumber(str_starts_with($mobile, '05') ? '+966'.substr($mobile, 1) : $mobile);
    }
}
