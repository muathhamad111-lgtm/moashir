<?php

declare(strict_types=1);

use App\Data\Entities\EntityNotice;
use App\Data\Mail\MailSample;
use App\Data\Mail\RenderedMail;
use App\Data\Mail\RequestedItem;
use App\Data\Mail\RequestedItems;
use App\Data\Notifications\NoticeText;
use App\Enums\Assessments\CompletionItemKind;
use App\Enums\Identity\Locale;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use App\Services\Notifications\MailPreviewRenderer;
use App\Support\Mail\SampleReplacements;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

const COMPLETION_TEXT = 'notifications.assessments.accredited.ACC-07.entity';

beforeEach(function (): void {
    Notification::fake();
});

function sentCompletionNotice(User $owner, NotificationCode $code): EntityNotification
{
    $sent = Notification::sent($owner, EntityNotification::class)
        ->first(static fn (EntityNotification $notification): bool => $notification->notice->code === $code);
    assert($sent instanceof EntityNotification);

    return $sent;
}

function renderedCompletionMail(EntityNotification $notification, User $owner, Locale $locale): RenderedMail
{
    return app(MailPreviewRenderer::class)->render(new MailSample('completion', $notification, $owner), $locale);
}

function sampleRecipient(): User
{
    return new User()->forceFill(['id' => 9001, 'name' => 'Sara', 'email' => 'sara@example.sa']);
}

/**
 * @param  list<RequestedItem>  $items
 */
function completionNoticeWith(array $items): EntityNotification
{
    $text = new NoticeText(COMPLETION_TEXT, SampleReplacements::for(COMPLETION_TEXT));

    return new EntityNotification(EntityNotice::fromText(NotificationCode::AccreditedCompletionRequested, $text, '/assessments')
        ->mailOnly()
        ->withItems(new RequestedItems($items)));
}

it('lists the items the assessor asked for in the ACC-07 e-mail, named from the request criteria version', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    $owner = ownerOf($assessment->entity);
    $policy = subOf($version, 'M1.1');

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [
        'notes' => 'يرجى إرفاق محضر اعتماد مجلس الإدارة للسياسة وتحديث وصفها.',
        'items' => [['sub_criterion_id' => $policy->id, 'kind' => 'cv'], ['sub_criterion_id' => $policy->id, 'kind' => 'evidence', 'note' => 'محضر <b>الاعتماد</b>']],
        'deadline_at' => Date::now()->addDays(7)->toIso8601String(),
        'expiry_outcome' => 'continue_as_submitted',
    ])->assertOk();

    $notification = sentCompletionNotice($owner, NotificationCode::AccreditedCompletionRequested);
    $mail = renderedCompletionMail($notification, $owner, Locale::Arabic);
    $items = $notification->notice->blocks->items->items ?? [];

    expect($items)->toHaveCount(2)
        ->and($items[0]->code)->toBe('M1.1')
        ->and($items[0]->name?->equals($policy->name))->toBeTrue()
        ->and($items[0]->kind)->toBe(CompletionItemKind::Cv)
        ->and($items[1]->note)->toBe('محضر <b>الاعتماد</b>')
        ->and($mail->html)->toContain(e(__('mail.requested_items.heading', ['count' => 2], 'ar')))
        ->and($mail->html)->toContain('<bdi dir="ltr">M1.1</bdi> '.e($policy->name->in('ar')))
        ->and($mail->html)->toContain('محضر &lt;b&gt;الاعتماد&lt;/b&gt;')
        ->and($mail->html)->not->toContain('<b>الاعتماد</b>')
        ->and($mail->text)->toContain('2. M1.1 '.$policy->name->in('ar').' ('.__('mail.requested_items.kinds.evidence', [], 'ar').')');
});

it('lists the items the follower returned an award request for in the ADV-25 e-mail', function (): void {
    Date::setTestNow('2026-11-01 08:00:00');
    $version = publishedAccreditedCriteria();
    $assessment = submittedAdvancedRequest(openAwardCycle($version), $version);
    $owner = ownerOf($assessment->entity);

    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/return-for-completion", [
        'notes' => RETURN_NOTES,
        'items' => [['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'cv', 'note' => 'حدّثوا وصف السياسة المعتمدة.']],
        'deadline_at' => '2026-11-15T08:00:00+00:00',
        'expiry_outcome' => 'continue_as_submitted',
    ])->assertOk();

    $mail = renderedCompletionMail(sentCompletionNotice($owner, NotificationCode::AdvancedReturnedForCompletion), $owner, Locale::English);

    expect($mail->html)->toContain(e(__('mail.requested_items.heading', ['count' => 1], 'en')))
        ->and($mail->html)->toContain('<p class="mail-item-kind"')
        ->and($mail->html)->toContain('حدّثوا وصف السياسة المعتمدة.')
        ->and($mail->text)->toContain('1. M1.1 '.subOf($version, 'M1.1')->name->in('en').' ('.__('mail.requested_items.kinds.cv', [], 'en').')');
});

it('writes the first ten items into the e-mail and counts the rest', function (Locale $locale): void {
    $items = array_map(
        static fn (int $index): RequestedItem => new RequestedItem('M'.$index.'.1', new LocalizedText('معيار '.$index, 'Criterion '.$index), CompletionItemKind::Evidence, null),
        range(1, 12),
    );
    $mail = renderedCompletionMail(completionNoticeWith($items), sampleRecipient(), $locale);
    $more = trans_choice('mail.requested_items.more', 2, [], $locale->value);

    expect(substr_count($mail->html, '<span class="mail-item-number"'))->toBe(RequestedItems::SHOWN)
        ->and($mail->html)->toContain(e(__('mail.requested_items.heading', ['count' => 12], $locale->value)))
        ->and($mail->html)->toContain('M10.1')
        ->and($mail->html)->not->toContain('M11.1')
        ->and($mail->html)->toContain(e($more))
        ->and($mail->text)->toContain($more);
})->with(Locale::cases());

it('names an item by its code alone when its sub-criterion left the version, and leaves out an empty note', function (): void {
    $mail = renderedCompletionMail(
        completionNoticeWith([new RequestedItem('M9.9', null, CompletionItemKind::Cv, '  ')]),
        sampleRecipient(),
        Locale::Arabic,
    );

    expect($mail->html)->toContain('<bdi dir="ltr">M9.9</bdi></p>')
        ->and($mail->html)->not->toContain('class="mail-item-note"')
        ->and($mail->text)->toContain('1. M9.9 ('.__('mail.requested_items.kinds.cv', [], 'ar').')');
});

it('leaves out the items list for a notice without items', function (): void {
    $mail = renderedCompletionMail(completionNoticeWith([]), sampleRecipient(), Locale::Arabic);

    expect($mail->html)->not->toContain('class="mail-items"')
        ->and($mail->text)->not->toContain(__('mail.requested_items.heading', ['count' => 0], 'ar'));
});

it('keeps the items when the queued notification is serialized', function (): void {
    $notification = completionNoticeWith([new RequestedItem('M1.1', new LocalizedText('سياسة', 'Policy'), CompletionItemKind::Evidence, 'note')]);
    $restored = unserialize(serialize($notification));
    assert($restored instanceof EntityNotification);

    expect($restored->notice->blocks->items?->items[0]->name?->in('en'))->toBe('Policy')
        ->and($restored->notice->blocks->items?->items[0]->kind)->toBe(CompletionItemKind::Evidence);
});
