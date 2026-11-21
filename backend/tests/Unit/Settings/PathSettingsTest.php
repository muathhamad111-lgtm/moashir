<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Settings\PathEntityText;
use App\Enums\Settings\PathSetting;
use App\Enums\Settings\SettingKey;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\PathEntityTexts;

it('maps every path setting to a distinct key per path', function (): void {
    $keys = [];

    foreach (PathSetting::cases() as $setting) {
        foreach (PathSetting::paths() as $path) {
            $keys[] = $setting->keyFor($path)->value;
        }
    }

    expect(array_unique($keys))->toHaveCount(count(PathSetting::cases()) * 2)
        ->and(PathSetting::ReviewSlaDays->keyFor(AssessmentPath::Accredited))->toBe(SettingKey::AccreditedReviewSlaDays)
        ->and(PathSetting::ReviewSlaDays->keyFor(AssessmentPath::Advanced))->toBe(SettingKey::AdvancedInitialReviewSlaDays)
        ->and(PathSetting::pathValues())->toBe(['accredited', 'advanced']);
});

it('refuses settings for the self assessment path', function (): void {
    PathSetting::UploadMaxFileMb->keyFor(AssessmentPath::SelfAssessment);
})->throws(LogicException::class);

it('reads, replaces and serialises the entity texts of a path', function (): void {
    $texts = PathEntityTexts::fromStored([
        'intro' => ['ar' => 'مقدمة', 'en' => 'Intro'],
        'instructions' => ['ar' => '', 'en' => 'Incomplete'],
        'unknown' => ['ar' => 'غير معروف', 'en' => 'Unknown'],
    ]);

    expect($texts->toArray())->toBe(['intro' => ['ar' => 'مقدمة', 'en' => 'Intro'], 'instructions' => null, 'after_submission' => null])
        ->and($texts->of(PathEntityText::Intro)?->en)->toBe('Intro');

    $changed = $texts->replacing(['intro' => null, 'after_submission' => new LocalizedText('شكرًا', 'Thank you')]);

    expect($changed->toArray())->toBe(['intro' => null, 'instructions' => null, 'after_submission' => ['ar' => 'شكرًا', 'en' => 'Thank you']])
        ->and($texts->of(PathEntityText::Intro))->not->toBeNull()
        ->and(PathEntityTexts::empty()->toArray())->toBe(['intro' => null, 'instructions' => null, 'after_submission' => null])
        ->and(PathEntityTexts::fromStored('broken')->toArray())->toBe(PathEntityTexts::empty()->toArray());
});
