<?php

declare(strict_types=1);

use App\Data\Assessments\ReviewNoteChange;
use App\Data\Assessments\ReviewNoteChanges;
use App\Data\Assessments\ReviewNoteUnit;
use App\Enums\Assessments\ReviewNoteAuthorRole;
use App\Enums\Assessments\ReviewNoteLevel;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\AssessmentReviewNote;
use App\Services\Assessments\ReviewNoteSheetReader;
use App\Services\Assessments\ReviewNoteSheetWriter;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

function reviewNoteChanges(ReviewNoteUnit $unit, ?string $body): ReviewNoteChanges
{
    return new ReviewNoteChanges([new ReviewNoteChange($unit, $body)]);
}

it('writes a first version, skips an unchanged body, supersedes a changed one and clears a blank one', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $author = staffUser(RoleKey::RequestFollower);
    $writer = app(ReviewNoteSheetWriter::class);
    $unit = new ReviewNoteUnit(ReviewNoteLevel::Main, mainOf($version, 'M1')->id);

    expect($writer->write($assessment, reviewNoteChanges($unit, ' أول نص '), $author))->toEqual([$unit])
        ->and($writer->write($assessment, reviewNoteChanges($unit, 'أول نص'), $author))->toBe([])
        ->and($writer->write($assessment, reviewNoteChanges($unit, 'نص ثان'), $author))->toEqual([$unit])
        ->and($writer->write($assessment, reviewNoteChanges($unit, ''), $author))->toEqual([$unit])
        ->and($writer->write($assessment, reviewNoteChanges($unit, null), $author))->toBe([]);

    $rows = AssessmentReviewNote::query()->orderBy('id')->get();

    expect($rows->pluck('body')->all())->toBe(['أول نص', 'نص ثان'])
        ->and($rows->pluck('version')->all())->toBe([1, 2])
        ->and($rows->every(static fn (AssessmentReviewNote $note): bool => $note->superseded_at !== null))->toBeTrue()
        ->and($rows->first()?->author_role)->toBe(ReviewNoteAuthorRole::Follower);

    $writer->write($assessment, reviewNoteChanges($unit, 'نص بعد المسح'), $author);

    expect(AssessmentReviewNote::query()->current()->sole()->version)->toBe(3);
});

it('keeps one current note per author and unit and never touches another author note', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $follower = staffUser(RoleKey::RequestFollower);
    $admin = staffUser(RoleKey::SystemAdmin);
    $writer = app(ReviewNoteSheetWriter::class);
    $sub = subOf($version, 'M1.2');
    $point = new ReviewNoteUnit(ReviewNoteLevel::Point, $sub->main_criterion_id, $sub->id, pointOf($version, 'M1.2.1')->id);
    $subUnit = new ReviewNoteUnit(ReviewNoteLevel::Sub, $sub->main_criterion_id, $sub->id);

    $writer->write($assessment, new ReviewNoteChanges([new ReviewNoteChange($point, 'نص المتابع'), new ReviewNoteChange($subUnit, 'نص المتابع على المعيار')]), $follower);
    $writer->write($assessment, reviewNoteChanges($point, 'نص المدير'), $admin);
    $writer->write($assessment, reviewNoteChanges($point, ''), $admin);
    $writer->write($assessment, reviewNoteChanges($point, 'نص المدير الجديد'), $admin);

    $current = AssessmentReviewNote::query()->current()->orderBy('id')->get();

    expect($current->map(static fn (AssessmentReviewNote $note): string => $note->author_role->value.':'.$note->unit()->key())->all())->toBe([
        'follower:'.$point->key(),
        'follower:'.$subUnit->key(),
        'admin:'.$point->key(),
    ])->and(AssessmentReviewNote::query()->where('author_user_id', $follower->id)->whereNotNull('superseded_at')->count())->toBe(0);
});

it('reads the current notes with their authors ordered by level, criterion ids and time', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $first = staffUser(RoleKey::RequestFollower);
    $second = staffUser(RoleKey::SystemAdmin);
    $writer = app(ReviewNoteSheetWriter::class);
    $sub = subOf($version, 'M1.2');
    $point = new ReviewNoteUnit(ReviewNoteLevel::Point, $sub->main_criterion_id, $sub->id, pointOf($version, 'M1.2.1')->id);
    $main = new ReviewNoteUnit(ReviewNoteLevel::Main, mainOf($version, 'M2')->id);
    $subUnit = new ReviewNoteUnit(ReviewNoteLevel::Sub, $sub->main_criterion_id, $sub->id);

    Date::setTestNow('2026-11-20 08:00:00');
    $writer->write($assessment, reviewNoteChanges($point, 'مؤشر'), $first);
    Date::setTestNow('2026-11-20 09:00:00');
    $writer->write($assessment, reviewNoteChanges($subUnit, 'معيار فرعي - ثان'), $second);
    Date::setTestNow('2026-11-20 10:00:00');
    $writer->write($assessment, reviewNoteChanges($main, 'معيار رئيسي'), $first);
    $writer->write($assessment, reviewNoteChanges($subUnit, 'معيار فرعي - أول'), $first);
    $writer->write($assessment, reviewNoteChanges($main, 'معيار رئيسي معدل'), $first);

    $notes = app(ReviewNoteSheetReader::class)->current($assessment);

    expect(array_map(static fn (AssessmentReviewNote $note): string => $note->body, $notes))->toBe(['معيار رئيسي معدل', 'معيار فرعي - ثان', 'معيار فرعي - أول', 'مؤشر'])
        ->and($notes[0]->relationLoaded('author'))->toBeTrue()
        ->and($notes[1]->author->id)->toBe($second->id)
        ->and(array_keys(app(ReviewNoteSheetReader::class)->currentByUnitOf($assessment, $first)))->toEqualCanonicalizing([$point->key(), $main->key(), $subUnit->key()]);
});

it('refuses a unit whose ids do not match its level', function (ReviewNoteLevel $level, ?int $sub, ?int $point): void {
    expect(fn (): ReviewNoteUnit => new ReviewNoteUnit($level, 1, $sub, $point))->toThrow(InvalidArgumentException::class);
})->with([
    'main with a sub' => [ReviewNoteLevel::Main, 2, null],
    'sub without a sub' => [ReviewNoteLevel::Sub, null, null],
    'sub with a point' => [ReviewNoteLevel::Sub, 2, 3],
    'point without a point' => [ReviewNoteLevel::Point, 2, null],
]);
