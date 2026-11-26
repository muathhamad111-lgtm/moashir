<?php

declare(strict_types=1);

use App\Enums\Identity\TermsKind;
use App\Models\Identity\TermsAcceptance;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Services\Identity\TermsAcceptanceRecorder;
use Illuminate\Support\Facades\Date;

/**
 * Publishes an agreement version as the current one, superseding the previous current version.
 */
function publishedNda(string $version = '1.0'): TermsVersion
{
    TermsVersion::query()->current()->where('kind', TermsKind::Nda->value)->get()
        ->each(static fn (TermsVersion $previous): bool => $previous->forceFill(['is_current' => false])->save());

    return TermsVersion::query()->forceCreate([
        'kind' => TermsKind::Nda->value,
        'version' => $version,
        'title' => ['ar' => 'اتفاقية عدم الإفصاح', 'en' => 'Non-disclosure agreement'],
        'body' => ['ar' => "## الغرض\nتحمي هذه الاتفاقية المعلومات السرية في منصة مؤشر.", 'en' => "## Purpose\nThis agreement protects confidential information on the Moashir platform."],
        'change_summary' => ['ar' => 'إضافة بند يمنع تصوير الشاشة.', 'en' => 'A clause that forbids screenshots was added.'],
        'effective_from' => Date::today()->toDateString(),
        'is_current' => true,
        'published_at' => Date::now(),
        'published_by_user_id' => User::factory()->create(['name' => 'هند الزهراني'])->id,
    ]);
}

function ndaDraft(string $version = '1.1'): TermsVersion
{
    return legalDraft(TermsKind::Nda, $version);
}

function acceptNdaAs(User $user, TermsVersion $version): TermsAcceptance
{
    return app(TermsAcceptanceRecorder::class)->acceptNda($user, $version);
}

/**
 * @return array<string, mixed>
 */
function ndaPublishPayload(array $overrides = []): array
{
    return [
        'change_summary' => ['ar' => 'إضافة بند يمنع تصوير الشاشة والشواهد.', 'en' => 'A clause that forbids screenshots of screens and evidence was added.'],
        'reason' => 'تحديث بنود السرية بعد مراجعة الشؤون القانونية.',
        'confirm_irreversible' => true,
        ...$overrides,
    ];
}
