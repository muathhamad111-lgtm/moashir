<?php

declare(strict_types=1);

use App\Enums\Contact\ContactEnquiryType;
use App\Models\Contact\ContactMessage;
use App\Notifications\Contact\ContactAcknowledgementNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('accepts a personal data request from a guest and labels it in the acknowledgement', function (): void {
    asGuest()->withHeaders(['Accept-Language' => 'en'])->postJson('/api/v1/contact-messages', contactPayload(['enquiry_type' => 'privacy_request']))->assertCreated();

    expect(ContactMessage::query()->sole()->enquiry_type)->toBe(ContactEnquiryType::PrivacyRequest)
        ->and(__('contact.enquiry_types.privacy_request', [], 'ar'))->toBe('طلب يتعلق بالبيانات الشخصية')
        ->and(__('contact.enquiry_types.privacy_request', [], 'en'))->toBe('Personal data request');
    Notification::assertSentOnDemand(ContactAcknowledgementNotification::class, static fn (ContactAcknowledgementNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routeNotificationFor('mail') === 'sara.alabdullah@example.sa');
});

it('accepts a personal data request from a signed-in entity and its follow-up in the portal', function (): void {
    $owner = ownerOf(entityWithOwner());

    $id = switchTo($owner)->postJson('/api/v1/contact-messages', contactPayload(['enquiry_type' => 'privacy_request']))->assertCreated()->json('data.id');

    switchTo($owner)->getJson('/api/v1/me/contact-messages/'.$id)->assertOk()->assertJsonPath('data.enquiry_type', 'privacy_request');
    switchTo($owner)->postJson('/api/v1/me/contact-messages/'.$id.'/replies', ['body' => 'أرجو تزويدي بنسخة من بيانات جهتنا.'])->assertCreated()
        ->assertJsonPath('data.enquiry_type', 'privacy_request');
});

it('filters the inbox by the personal data request type', function (): void {
    contactMessage(['enquiry_type' => ContactEnquiryType::PrivacyRequest]);
    contactMessage(['enquiry_type' => ContactEnquiryType::Payments]);

    signIn(contentOfficer())->getJson('/api/v1/admin/contact-messages?filter[enquiry_type]=privacy_request')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.enquiry_type', 'privacy_request');
});

it('still refuses an unknown enquiry type', function (): void {
    submitContactAsGuest(['enquiry_type' => 'data_request'])->assertUnprocessable()->assertJsonValidationErrors('enquiry_type');
});
