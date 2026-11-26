<?php

declare(strict_types=1);

use App\Enums\Cms\CmsPageKey;
use App\Models\Award\AwardCycle;
use Illuminate\Support\Facades\DB;

const DEMO_HERO_CAPTION = ['ar' => 'مثال توضيحي لنتيجة جهة افتراضية', 'en' => 'Illustrative result of a sample entity'];

const NEUTRAL_HERO_CAPTION = ['ar' => 'النتيجة حسب المعايير الرئيسية', 'en' => 'Result by main criterion'];

function runMigration(string $file, string $direction = 'up'): void
{
    $migration = require database_path('migrations/'.$file.'.php');
    $migration->{$direction}();
}

/**
 * @param  array<string, mixed>  $content
 */
function publishedHomeContent(array $content): void
{
    DB::table('cms_pages')->where('key', CmsPageKey::Home->value)->update([
        'is_published' => true,
        'published_at' => now(),
        'published_content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
}

/**
 * @return array<string, mixed>
 */
function publishedHome(): array
{
    $content = json_decode((string) DB::table('cms_pages')->where('key', CmsPageKey::Home->value)->value('published_content'), true);

    return is_array($content) ? $content : [];
}

it('turns every demo availability into live in drafts and in the published page, and leaves the other values', function (): void {
    $demoSection = cmsSection(CmsPageKey::Home, ['key' => 'paths', 'meta' => ['availability' => 'demo', 'accent' => 'calm']]);
    $soonSection = cmsSection(CmsPageKey::Home, ['key' => 'kit', 'meta' => ['availability' => 'coming_soon']]);
    $demoItem = cmsItem($demoSection, ['meta' => ['availability' => 'demo']]);
    publishedHomeContent(['key' => 'home', 'sections' => [
        ['key' => 'paths', 'meta' => ['availability' => 'demo'], 'items' => [['id' => 1, 'meta' => ['availability' => 'demo', 'points' => []]]]],
        ['key' => 'kit', 'meta' => ['availability' => 'coming_soon'], 'items' => []],
    ]]);

    runMigration('2026_11_12_100100_replace_demo_cms_availability_with_live');

    expect($demoSection->refresh()->meta)->toEqual(['accent' => 'calm', 'availability' => 'live'])
        ->and($soonSection->refresh()->meta)->toBe(['availability' => 'coming_soon'])
        ->and($demoItem->refresh()->meta)->toBe(['availability' => 'live'])
        ->and(publishedHome()['sections'][0]['meta']['availability'])->toBe('live')
        ->and(publishedHome()['sections'][0]['items'][0]['meta'])->toEqual(['availability' => 'live', 'points' => []])
        ->and(publishedHome()['sections'][1]['meta']['availability'])->toBe('coming_soon')
        ->and(json_encode(publishedHome()))->not->toContain('"demo"');
});

it('replaces the shipped demo wording only where the row still holds the shipped default, and restores it on rollback', function (): void {
    $hero = cmsSection(CmsPageKey::Home, ['key' => 'hero', 'type' => 'hero', 'meta' => ['preview' => ['caption' => DEMO_HERO_CAPTION, 'score' => 78]]]);
    $edited = cmsSection(CmsPageKey::Home, ['key' => 'hero-two', 'type' => 'hero', 'meta' => ['preview' => ['caption' => ['ar' => 'نص حرّره الفريق', 'en' => 'Team text']]]]);
    publishedHomeContent(['key' => 'home', 'sections' => [['key' => 'hero', 'type' => 'hero', 'meta' => ['preview' => ['caption' => DEMO_HERO_CAPTION]], 'items' => []]]]);
    $cycle = AwardCycle::factory()->create(['name' => ['ar' => 'الدورة التجريبية لجائزة التميز المؤسسي', 'en' => 'Institutional Excellence Award - demonstration cycle']]);
    $otherCycle = AwardCycle::factory()->create(['criteria_version_id' => $cycle->criteria_version_id]);
    DB::table('maturity_interpretations')->where('band', 'leading')->whereNull('main_criterion_code')->update(['text' => json_encode([
        'ar' => 'الممارسات متقدمة ومتكاملة وتصلح نموذجًا يُحتذى في القطاع (نص تجريبي).',
        'en' => 'Practices are advanced and integrated and can serve as a model for the sector (demo text).',
    ], JSON_UNESCAPED_UNICODE)]);
    DB::table('maturity_interpretations')->where('band', 'mature')->whereNull('main_criterion_code')->update(['text' => json_encode(['ar' => 'نص معتمد من الإدارة', 'en' => 'Approved text'], JSON_UNESCAPED_UNICODE)]);

    runMigration('2026_11_12_100200_neutralize_shipped_demo_wording');

    $leading = json_decode((string) DB::table('maturity_interpretations')->where('band', 'leading')->whereNull('main_criterion_code')->value('text'), true);
    $mature = json_decode((string) DB::table('maturity_interpretations')->where('band', 'mature')->whereNull('main_criterion_code')->value('text'), true);

    expect($hero->refresh()->meta)->toEqual(['preview' => ['score' => 78, 'caption' => NEUTRAL_HERO_CAPTION]])
        ->and($edited->refresh()->meta['preview']['caption'])->toBe(['ar' => 'نص حرّره الفريق', 'en' => 'Team text'])
        ->and(publishedHome()['sections'][0]['meta']['preview']['caption'])->toBe(NEUTRAL_HERO_CAPTION)
        ->and($cycle->refresh()->name->toArray())->toBe(['ar' => 'دورة جائزة التميز المؤسسي', 'en' => 'Institutional Excellence Award cycle'])
        ->and($otherCycle->refresh()->name->ar)->toBe('الدورة الأولى لجائزة التميز المؤسسي')
        ->and($leading)->toBe(['ar' => 'الممارسات متقدمة ومتكاملة وتصلح نموذجًا يُحتذى في القطاع.', 'en' => 'Practices are advanced and integrated and can serve as a model for the sector.'])
        ->and($mature)->toBe(['ar' => 'نص معتمد من الإدارة', 'en' => 'Approved text']);

    runMigration('2026_11_12_100200_neutralize_shipped_demo_wording', 'down');

    expect($hero->refresh()->meta['preview']['caption'])->toBe(DEMO_HERO_CAPTION)
        ->and($cycle->refresh()->name->en)->toBe('Institutional Excellence Award - demonstration cycle');
});

it('rolls the demo-wording schema migrations back and forward again', function (string $file, string $constraint, string $added): void {
    $definition = static fn (): string => (string) DB::scalar('select pg_get_constraintdef(oid) from pg_constraint where conname = ?', [$constraint]);

    runMigration($file, 'down');
    expect($definition())->not->toContain($added);

    runMigration($file);
    expect($definition())->toContain($added);
})->with([
    'unverifiable verification outcome' => ['2026_11_12_100000_add_unverifiable_outcome_to_certificate_verification_logs', 'certificate_verification_logs_outcome_check', 'unverifiable'],
    'demo purge housekeeping task' => ['2026_11_12_100300_add_demo_purge_task_to_housekeeping_runs', 'housekeeping_runs_task_check', 'demo_purge'],
    'unprefixed test certificate numbers' => ['2026_11_12_100400_allow_unprefixed_numbers_for_test_certificates', 'certificates_number_check', 'is_test OR'],
]);
