<?php

declare(strict_types=1);

use App\Enums\Cms\CmsSectionType;
use App\Rules\BoundedContentMeta;
use App\Rules\FontAwesomeIcon;
use App\Rules\SafeLinkUrl;
use App\Services\Demo\DemoContentSource;
use App\Support\Cms\Meta\MetaFieldSet;
use App\Support\Cms\MetaSchemas\SectionMetaSchemas;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

/**
 * @return array<string, mixed>
 */
function demoPublicContent(): array
{
    return json_decode((string) file_get_contents(database_path('data/demo/public-content.json')), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @return array<string, mixed>
 */
function landingContent(): array
{
    return json_decode((string) file_get_contents(database_path('data/content/landing-home.json')), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @return list<array<string, mixed>>
 */
function demoHomeSections(): array
{
    return landingContent()['page']['sections'];
}

/**
 * @return list<string>
 */
function shippedPublicTexts(): array
{
    $texts = [];

    $content = [demoPublicContent(), landingContent()];

    array_walk_recursive($content, static function (mixed $value) use (&$texts): void {
        if (is_string($value)) {
            $texts[] = $value;
        }
    });

    return $texts;
}

function passesRule(ValidationRule $rule, mixed $value): bool
{
    $passes = true;
    $rule->validate('field', $value, static function () use (&$passes): void {
        $passes = false;
    });

    return $passes;
}

it('orders the landing home page like the approved v5 contract', function (): void {
    expect(array_column(demoHomeSections(), 'key'))->toBe([
        'hero', 'stats', 'about', 'model', 'paths', 'journey', 'mid_cta', 'levels', 'kit', 'lenses', 'leaders', 'partners', 'start', 'contact', 'assistant',
    ])->and(array_column(demoHomeSections(), 'type'))->toBe([
        'hero', 'kpi_promo', 'about', 'excellence_model', 'paths', 'how_it_works', 'rich_text', 'levels', 'bag_promo', 'lenses_brief', 'leadership_promo', 'partners', 'rich_text', 'contact', 'assistant',
    ]);
});

it('keeps the home page in the landing file only and merges it into the demo content', function (): void {
    $merged = app(DemoContentSource::class)->read();

    expect(demoPublicContent()['pages'])->not->toHaveKey('home')
        ->and($merged['pages']['home'])->toBe(landingContent()['page'])
        ->and($merged['site_settings']['header_links'])->toBe(landingContent()['header_links'])
        ->and($merged['site_settings']['quick_links'])->toBe(landingContent()['quick_links']);
});

it('describes the designed sections with their variants, roles and counts', function (): void {
    $sections = collect(demoHomeSections())->keyBy('key');
    $rolesOf = static fn (string $key): array => array_count_values(array_column(array_column($sections[$key]['items'], 'meta'), 'role'));

    expect($sections['journey']['meta']['variant'])->toBe('journey')
        ->and($sections['journey']['items'])->toHaveCount(6)
        ->and($sections['mid_cta']['meta']['variant'])->toBe('band')
        ->and($sections['start']['meta']['variant'])->toBe('cta')
        ->and($sections['hero']['meta'])->toHaveKeys(['eyebrow', 'highlight', 'score_caption'])->not->toHaveKey('preview')
        ->and($rolesOf('hero'))->toBe(['secondary-cta' => 1])
        ->and($rolesOf('about'))->toBe(['audience' => 3, 'goal' => 6])
        ->and($rolesOf('kit'))->toBe(['step' => 4, 'tool' => 4])
        ->and(array_column(array_column($sections['paths']['items'], 'meta'), 'path'))->toBe(['self', 'accredited', 'advanced'])
        ->and($sections['levels']['meta']['sample_score'])->toBe(84)
        ->and($sections['lenses']['items'])->toHaveCount(5)
        ->and($sections['assistant']['items'])->toHaveCount(3)
        ->and($sections['assistant']['title'])->toBe(['ar' => 'مساعد مؤشر', 'en' => 'Moashir assistant']);
});

it('ships the partners section disabled with titled logos and no image', function (): void {
    $partners = collect(demoHomeSections())->keyBy('key')['partners'];

    expect($partners['enabled'])->toBeFalse()
        ->and($partners['items'])->toHaveCount(4)
        ->and(array_column(array_column($partners['items'], 'title'), 'ar'))->toContain('وزارة الصحة', 'وزارة التعليم');

    foreach ($partners['items'] as $item) {
        expect($item)->not->toHaveKey('image_file_id');
    }
});

it('passes the per-type meta rules of every shipped section and item', function (): void {
    foreach (demoHomeSections() as $section) {
        $schema = SectionMetaSchemas::of(CmsSectionType::from($section['type']));
        $nodes = [[$schema->sectionFields(), $section], ...array_map(static fn (array $item): array => [$schema->itemFields(), $item], $section['items'])];

        foreach ($nodes as [$fields, $node]) {
            $validator = Validator::make(['meta' => $node['meta'] ?? []], (new MetaFieldSet($fields))->rules());
            expect($validator->errors()->all())->toBe([], $section['key']);
        }
    }
});

it('links the header and the footer to the landing anchors', function (): void {
    $anchors = ['model', 'paths', 'levels', 'kit', 'lenses', 'contact'];

    expect(array_column(landingContent()['header_links'], 'url'))->toBe(array_map(static fn (string $anchor): string => '/#'.$anchor, $anchors))
        ->and(array_column(landingContent()['quick_links'], 'url'))->toBe(array_map(static fn (string $anchor): string => '#'.$anchor, $anchors))
        ->and(landingContent()['header_links'][3]['label'])->toBe(['ar' => 'حقيبة التميز السريع', 'en' => 'Quick Excellence Kit']);
});

it('uses the approved wording only', function (): void {
    $content = implode("\n", shippedPublicTexts());

    foreach (['تحكيم', 'محكم', 'فاتورة', 'فواتير', 'مستشار', 'Judge', 'Jury', 'Juror', 'invoice', 'Consultant', 'Excellence Bag', 'Muasher', 'Moasher'] as $forbidden) {
        expect($content)->not->toContain($forbidden);
    }

    expect($content)->toContain('Moashir')
        ->and(preg_match('/[\x{0660}-\x{0669}]/u', $content))->toBe(0);
});

it('carries no demo, trial or designer-annotation wording and never claims an AI assistant', function (): void {
    $content = implode("\n", shippedPublicTexts());

    foreach (['تجريبي', 'تجريبية', 'تجربة', 'اختبار', 'توضيحي', 'مثال', 'عينة', 'لوحة التحكم', 'الذكي', 'demo', 'Demo', 'trial', 'Trial', 'sample', 'Sample', 'Illustrative', ' AI'] as $forbidden) {
        expect($content)->not->toContain($forbidden);
    }
});

it('keeps every link, icon and meta object editable through the content API', function (): void {
    $pages = demoPublicContent()['pages'];
    $sections = [...demoHomeSections(), ...array_merge(...array_values(array_map(static fn (array $page): array => $page['sections'], $pages)))];

    foreach ($sections as $section) {
        foreach ([$section, ...($section['items'] ?? [])] as $node) {
            if (isset($node['cta_url'])) {
                expect(passesRule(new SafeLinkUrl, $node['cta_url']))->toBeTrue($node['cta_url']);
            }

            if (isset($node['icon'])) {
                expect(passesRule(new FontAwesomeIcon, $node['icon']))->toBeTrue($node['icon']);
            }

            if (isset($node['meta'])) {
                expect(passesRule(new BoundedContentMeta(16384), $node['meta']))->toBeTrue($section['key']);
            }
        }
    }
});
