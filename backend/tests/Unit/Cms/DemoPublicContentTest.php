<?php

declare(strict_types=1);

use App\Rules\BoundedContentMeta;
use App\Rules\FontAwesomeIcon;
use App\Rules\SafeLinkUrl;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * @return array<string, mixed>
 */
function demoPublicContent(): array
{
    return json_decode((string) file_get_contents(database_path('data/demo/public-content.json')), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @return list<array<string, mixed>>
 */
function demoHomeSections(): array
{
    return demoPublicContent()['pages']['home']['sections'];
}

function passesRule(ValidationRule $rule, mixed $value): bool
{
    $passes = true;
    $rule->validate('field', $value, static function () use (&$passes): void {
        $passes = false;
    });

    return $passes;
}

it('orders the demo home page like the approved brief', function (): void {
    expect(array_column(demoHomeSections(), 'key'))->toBe([
        'hero', 'how_it_works', 'paths', 'excellence_model', 'journey', 'bag_promo', 'cta_banner', 'leadership_promo', 'contact',
    ]);
});

it('describes the journey, the call to action and the hero for the designed sections', function (): void {
    $sections = collect(demoHomeSections())->keyBy('key');

    expect($sections['journey']['type'])->toBe('how_it_works')
        ->and($sections['journey']['meta']['variant'])->toBe('journey')
        ->and($sections['journey']['items'])->toHaveCount(6)
        ->and($sections['cta_banner']['type'])->toBe('rich_text')
        ->and($sections['cta_banner']['meta']['variant'])->toBe('cta')
        ->and($sections['hero']['meta'])->toHaveKeys(['eyebrow', 'highlight', 'preview'])
        ->and(array_count_values(array_column(array_column($sections['hero']['items'], 'meta'), 'role')))->toBe(['secondary-cta' => 1, 'point' => 3])
        ->and($sections['how_it_works']['items'])->toHaveCount(5);

    foreach (['how_it_works', 'paths', 'excellence_model', 'journey', 'bag_promo', 'contact'] as $key) {
        expect($sections[$key]['meta']['eyebrow'])->toHaveKeys(['ar', 'en']);
    }
});

it('marks the advanced path and the Kit as coming soon and never as demo', function (): void {
    $sections = collect(demoHomeSections())->keyBy('key');
    $availability = array_column(array_column($sections['paths']['items'], 'meta'), 'availability');

    expect($availability)->toBe(['live', 'live', 'coming_soon'])
        ->and($sections['bag_promo']['meta']['availability'])->toBe('coming_soon')
        ->and($sections['paths']['items'][2])->not->toHaveKey('cta_url')
        ->and(json_encode(demoPublicContent(), JSON_UNESCAPED_UNICODE))->not->toContain('"availability":"demo"');
});

it('uses the approved wording only', function (): void {
    $content = json_encode(demoPublicContent(), JSON_UNESCAPED_UNICODE);

    foreach (['تحكيم', 'محكم', 'فاتورة', 'فواتير', 'مستشار', 'Judge', 'Jury', 'Juror', 'invoice', 'Consultant', 'Excellence Bag', 'Muasher', 'Moasher'] as $forbidden) {
        expect($content)->not->toContain($forbidden);
    }

    expect($content)->toContain('Moashir')
        ->and(preg_match('/[\x{0660}-\x{0669}]/u', $content))->toBe(0);
});

it('carries no demo or trial wording and labels the hero preview neutrally', function (): void {
    $content = json_encode(demoPublicContent(), JSON_UNESCAPED_UNICODE);
    $hero = collect(demoHomeSections())->keyBy('key')['hero'];

    foreach (['تجريبي', 'تجريبية', 'توضيحي', 'مثال', 'عينة', 'demo', 'Demo', 'trial', 'Trial', 'sample', 'Sample', 'Illustrative'] as $forbidden) {
        expect($content)->not->toContain($forbidden);
    }

    expect($hero['meta']['preview']['caption'])->toBe(['ar' => 'النتيجة حسب المعايير الرئيسية', 'en' => 'Result by main criterion']);
});

it('keeps every link, icon and meta object editable through the content API', function (): void {
    foreach (demoHomeSections() as $section) {
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
