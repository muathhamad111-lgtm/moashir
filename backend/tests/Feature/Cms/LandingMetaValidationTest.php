<?php

declare(strict_types=1);

use App\Enums\Cms\CmsPageKey;
use App\Enums\Cms\CmsSectionType;
use App\Enums\Identity\RoleKey;
use App\Models\Cms\CmsSection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

const LANDING_ADMIN = '/api/v1/admin/content';

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @return array<string, mixed>
 */
function levelsSectionPayload(array $meta = []): array
{
    return [
        'key' => 'levels',
        'type' => 'levels',
        'title' => ['ar' => 'شهادة معتمدة', 'en' => 'An accredited certificate'],
        'meta' => [
            'eyebrow' => ['ar' => 'مستويات التميّز', 'en' => 'Excellence levels'],
            'highlight' => ['ar' => 'تعكس مستوى تميّزك', 'en' => 'that reflects your excellence level'],
            'result_label' => ['ar' => 'نتيجة التقييم المعتمد', 'en' => 'Accredited assessment result'],
            'sample_score' => 84,
            ...$meta,
        ],
    ];
}

function landingSection(CmsSectionType $type, string $key): CmsSection
{
    return cmsSection(CmsPageKey::Home, ['key' => $key, 'type' => $type]);
}

it('stores the about and levels section types the migration now allows', function (CmsSectionType $type): void {
    $section = landingSection($type, $type->value);

    expect(CmsSection::query()->findOrFail($section->id)->type)->toBe($type);
})->with([CmsSectionType::About, CmsSectionType::Levels]);

it('keeps refusing section types outside the database list', function (): void {
    $page = cmsPage();

    expect(fn () => DB::table('cms_sections')->insert(['cms_page_id' => $page->id, 'key' => 'carousel', 'type' => 'carousel', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('refuses to roll the type list back while about or levels sections exist, even removed ones', function (): void {
    $migration = require database_path('migrations/2026_11_17_100000_add_about_and_levels_cms_section_types.php');
    landingSection(CmsSectionType::Levels, 'levels')->delete();

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);

    CmsSection::withTrashed()->where('type', 'levels')->forceDelete();
    $migration->down();

    expect(fn () => DB::transaction(fn (): CmsSection => landingSection(CmsSectionType::About, 'about')))->toThrow(QueryException::class);

    $migration->up();

    expect(landingSection(CmsSectionType::About, 'about')->type)->toBe(CmsSectionType::About);
});

it('creates a levels section with typed meta and publishes it on the public home', function (): void {
    $officer = contentOfficer();

    signIn($officer)->postJson(LANDING_ADMIN.'/pages/home/sections', levelsSectionPayload())
        ->assertCreated()
        ->assertJsonPath('data.type', 'levels')
        ->assertJsonPath('data.meta.sample_score', 84)
        ->assertJsonPath('data.meta.eyebrow', ['ar' => 'مستويات التميّز', 'en' => 'Excellence levels']);

    publishPage(CmsPageKey::Home, $officer);

    $this->getJson('/api/v1/content/home')
        ->assertOk()
        ->assertJsonPath('data.sections.0.type', 'levels')
        ->assertJsonPath('data.sections.0.meta.sample_score', 84);
});

it('creates an about section with its audience and goal items', function (): void {
    $section = landingSection(CmsSectionType::About, 'about');
    signIn(contentOfficer());

    $this->patchJson(LANDING_ADMIN.'/sections/'.$section->id, ['meta' => [
        'eyebrow' => ['ar' => 'عن المنصة', 'en' => 'About the platform'],
        'key_words' => ['ar' => 'نضج، وتميز، أثر', 'en' => 'maturity, excellence, impact'],
        'audience_label' => ['ar' => 'تخدم المنصة', 'en' => 'The platform serves'],
        'goals_title' => ['ar' => 'أهداف المنصة', 'en' => 'Platform goals'],
    ]])->assertOk()->assertJsonPath('data.meta.goals_title.en', 'Platform goals');

    $this->postJson(LANDING_ADMIN.'/sections/'.$section->id.'/items', [
        'title' => ['ar' => 'الأوقاف', 'en' => 'Endowments'],
        'meta' => ['role' => 'audience', 'glyph' => 'endowment'],
    ])->assertCreated()->assertJsonPath('data.items.0.meta', ['role' => 'audience', 'glyph' => 'endowment']);
});

it('refuses invalid section meta for the section type', function (array $meta, string $field): void {
    signIn(contentOfficer());

    $this->postJson(LANDING_ADMIN.'/pages/home/sections', levelsSectionPayload($meta))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors([$field]);
})->with([
    'score above 100' => [['sample_score' => 101], 'meta.sample_score'],
    'negative score' => [['sample_score' => -1], 'meta.sample_score'],
    'score as text' => [['sample_score' => '84'], 'meta.sample_score'],
    'missing English' => [['hint' => ['ar' => 'اسحب المؤشر']], 'meta.hint.en'],
    'empty Arabic' => [['hint' => ['ar' => '', 'en' => 'Drag the slider']], 'meta.hint.ar'],
    'plain string label' => [['result_label' => 'نتيجة'], 'meta.result_label'],
    'unknown language' => [['eyebrow' => ['ar' => 'مستويات', 'en' => 'Levels', 'fr' => 'Niveaux']], 'meta.eyebrow'],
    'html in a label' => [['eyebrow' => ['ar' => '<b>مستويات</b>', 'en' => 'Levels']], 'meta.eyebrow.ar'],
    'script in a note' => [['verify_note' => ['ar' => 'رمز تحقق', 'en' => '<script>alert(1)</script>']], 'meta.verify_note.en'],
    'label too long' => [['result_label' => ['ar' => str_repeat('م', 121), 'en' => 'Result']], 'meta.result_label.ar'],
    'note too long' => [['settings_note' => ['ar' => str_repeat('م', 1001), 'en' => 'Settings']], 'meta.settings_note.ar'],
]);

it('limits the variant to the styles of the section type', function (CmsSectionType $type, string $variant, bool $isAllowed): void {
    $section = landingSection($type, 'styled');
    signIn(contentOfficer());

    $response = $this->patchJson(LANDING_ADMIN.'/sections/'.$section->id, ['meta' => ['variant' => $variant]]);

    $isAllowed ? $response->assertOk()->assertJsonPath('data.meta.variant', $variant) : $response->assertUnprocessable()->assertJsonValidationErrors(['meta.variant']);
})->with([
    'rich text band' => [CmsSectionType::RichText, 'band', true],
    'rich text cta' => [CmsSectionType::RichText, 'cta', true],
    'rich text journey' => [CmsSectionType::RichText, 'journey', false],
    'how it works journey' => [CmsSectionType::HowItWorks, 'journey', true],
    'how it works band' => [CmsSectionType::HowItWorks, 'band', false],
    'faq keeps a free variant' => [CmsSectionType::Faq, 'accordion', true],
]);

it('picks the item meta rules from the parent section type', function (CmsSectionType $type, array $meta, ?string $invalidField): void {
    $section = landingSection($type, 'parent');
    signIn(contentOfficer());

    $response = $this->postJson(LANDING_ADMIN.'/sections/'.$section->id.'/items', ['title' => ['ar' => 'عنصر', 'en' => 'Item'], 'meta' => $meta]);

    $invalidField === null ? $response->assertCreated() : $response->assertUnprocessable()->assertJsonValidationErrors([$invalidField]);
})->with([
    'about audience with a glyph' => [CmsSectionType::About, ['role' => 'audience', 'glyph' => 'people'], null],
    'about with an unknown glyph' => [CmsSectionType::About, ['role' => 'goal', 'glyph' => 'rocket'], 'meta.glyph'],
    'about with a kit role' => [CmsSectionType::About, ['role' => 'step'], 'meta.role'],
    'kit step' => [CmsSectionType::BagPromo, ['role' => 'step', 'glyph' => 'shield'], null],
    'kit audience role' => [CmsSectionType::BagPromo, ['role' => 'audience'], 'meta.role'],
    'hero point kept' => [CmsSectionType::Hero, ['role' => 'point'], null],
    'hero secondary call to action' => [CmsSectionType::Hero, ['role' => 'secondary-cta'], null],
    'hero goal role' => [CmsSectionType::Hero, ['role' => 'goal'], 'meta.role'],
    'rich text secondary call to action' => [CmsSectionType::RichText, ['role' => 'secondary-cta'], null],
    'model glyph' => [CmsSectionType::ExcellenceModel, ['glyph' => 'grid'], null],
    'model unknown glyph' => [CmsSectionType::ExcellenceModel, ['glyph' => 'Grid'], 'meta.glyph'],
    'paths self' => [CmsSectionType::Paths, ['path' => 'self', 'cost_note' => ['ar' => 'مجاني', 'en' => 'Free']], null],
    'paths unknown path' => [CmsSectionType::Paths, ['path' => 'award'], 'meta.path'],
    'paths cost note without English' => [CmsSectionType::Paths, ['path' => 'advanced', 'cost_note' => ['ar' => 'رسوم المشاركة']], 'meta.cost_note.en'],
    'journey step' => [CmsSectionType::HowItWorks, ['tags' => ['accredited', 'award'], 'screen' => 'review'], null],
    'journey unknown tag' => [CmsSectionType::HowItWorks, ['tags' => ['all', 'self']], 'meta.tags.1'],
    'journey repeated tag' => [CmsSectionType::HowItWorks, ['tags' => ['all', 'all']], 'meta.tags.0'],
    'journey empty tags' => [CmsSectionType::HowItWorks, ['tags' => []], 'meta.tags'],
    'journey four tags' => [CmsSectionType::HowItWorks, ['tags' => ['all', 'accredited', 'award', 'all']], 'meta.tags'],
    'journey tags as text' => [CmsSectionType::HowItWorks, ['tags' => 'all'], 'meta.tags'],
    'journey unknown screen' => [CmsSectionType::HowItWorks, ['screen' => 'payment'], 'meta.screen'],
    'faq passes unknown keys through' => [CmsSectionType::Faq, ['glyph' => 'rocket', 'role' => 'anything'], null],
]);

it('validates the item meta on update with the parent section type', function (): void {
    $section = landingSection(CmsSectionType::BagPromo, 'kit');
    $item = cmsItem($section);
    signIn(contentOfficer());

    $this->putJson(LANDING_ADMIN.'/sections/'.$section->id.'/items/'.$item->id, ['meta' => ['role' => 'tool', 'glyph' => 'map']])
        ->assertOk()->assertJsonPath('data.items.0.meta', ['role' => 'tool', 'glyph' => 'map']);
    $this->putJson(LANDING_ADMIN.'/sections/'.$section->id.'/items/'.$item->id, ['meta' => ['role' => 'tool', 'glyph' => 'compass']])
        ->assertUnprocessable()->assertJsonValidationErrors(['meta.glyph']);
});

it('cleans invisible characters from localized meta and keeps unknown keys', function (): void {
    $section = landingSection(CmsSectionType::Hero, 'hero');
    signIn(contentOfficer());

    $this->patchJson(LANDING_ADMIN.'/sections/'.$section->id, ['meta' => [
        'eyebrow' => ['ar' => "\u{202E}منصّة نموذج التميّز  ", 'en' => "Excellence model\u{200F}platform"],
        'preview' => ['score' => 78],
    ]])->assertOk()
        ->assertJsonPath('data.meta.eyebrow.ar', 'منصّة نموذج التميّز')
        ->assertJsonPath('data.meta.preview', ['score' => 78]);
});

it('names the meta fields in the request language', function (string $locale, string $message): void {
    signIn(contentOfficer());

    $errors = $this->withHeader('Accept-Language', $locale)
        ->postJson(LANDING_ADMIN.'/pages/home/sections', levelsSectionPayload(['hint' => ['ar' => 'اسحب المؤشر']]))
        ->assertUnprocessable()
        ->json('errors');

    expect($errors['meta.hint.en'][0])->toContain($message);
})->with([
    'Arabic' => ['ar', 'نص الإرشاد بالإنجليزية'],
    'English' => ['en', 'hint in English'],
]);

it('denies the typed meta endpoints to roles without content.home.manage', function (RoleKey $role): void {
    $section = landingSection(CmsSectionType::About, 'about');
    $item = cmsItem($section);
    signIn($role === RoleKey::Entity ? entityUser() : staffUser($role));

    $this->postJson(LANDING_ADMIN.'/pages/home/sections', levelsSectionPayload())->assertForbidden();
    $this->patchJson(LANDING_ADMIN.'/sections/'.$section->id, ['meta' => ['eyebrow' => ['ar' => 'عن المنصة', 'en' => 'About']]])->assertForbidden();
    $this->postJson(LANDING_ADMIN.'/sections/'.$section->id.'/items', ['meta' => ['role' => 'goal']])->assertForbidden();
    $this->putJson(LANDING_ADMIN.'/sections/'.$section->id.'/items/'.$item->id, ['meta' => ['glyph' => 'people']])->assertForbidden();
})->with([RoleKey::FinanceOfficer, RoleKey::Entity]);

it('asks guests to sign in on the editor and serves them the public home', function (): void {
    $section = landingSection(CmsSectionType::Levels, 'levels');

    $this->postJson(LANDING_ADMIN.'/pages/home/sections', levelsSectionPayload())->assertUnauthorized();
    $this->patchJson(LANDING_ADMIN.'/sections/'.$section->id, ['meta' => ['sample_score' => 50]])->assertUnauthorized();
    $this->postJson(LANDING_ADMIN.'/sections/'.$section->id.'/items', ['meta' => []])->assertUnauthorized();
    $this->getJson('/api/v1/content/home')->assertOk();
});
