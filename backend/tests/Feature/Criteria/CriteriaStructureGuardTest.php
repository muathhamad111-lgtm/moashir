<?php

declare(strict_types=1);

use App\Enums\Criteria\CriteriaEditKind;
use App\Enums\Criteria\CriteriaEditScope;
use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Criteria\CriteriaEditEvent;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\SubCriterion;
use App\Support\ValueObjects\Weight;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('rejects a structural change of a published criterion at the database level', function (): void {
    $published = publishVersion(balancedDraft());

    $mainId = mainOf($published, 'M1')->id;
    $pointId = pointOf($published, 'M1.1.1')->id;

    expect(fn () => DB::transaction(fn () => DB::table('main_criteria')->where('id', $mainId)->update(['weight' => 900])))
        ->toThrow(QueryException::class, 'criteria_structure');
    expect(fn () => DB::transaction(fn () => DB::table('guiding_points')->where('id', $pointId)->update(['is_active' => false])))
        ->toThrow(QueryException::class, 'criteria_structure');
});

it('rejects adding or deleting items of a published version at the database level', function (): void {
    $published = publishVersion(balancedDraft());
    $main = mainOf($published, 'M1');

    $subId = subOf($published, 'M1.2')->id;

    expect(fn () => DB::transaction(fn () => SubCriterion::factory()->for($main, 'mainCriterion')->create(['code' => 'M1.9', 'sort_order' => 9, 'weight' => Weight::of('10.00')])))
        ->toThrow(QueryException::class, 'criteria_structure');
    expect(fn () => DB::transaction(fn () => DB::table('sub_criteria')->where('id', $subId)->delete()))
        ->toThrow(QueryException::class, 'criteria_structure');
});

it('allows text columns of a published version to change only inside a text correction', function (): void {
    $published = publishVersion(balancedDraft());
    $mainId = mainOf($published, 'M1')->id;
    $name = json_encode(['ar' => 'القيادة', 'en' => 'Leadership'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

    expect(fn () => DB::transaction(fn () => DB::table('main_criteria')->where('id', $mainId)->update(['name' => $name])))->toThrow(QueryException::class, 'criteria_structure');

    DB::transaction(static function () use ($mainId, $name): void {
        DB::select("SELECT set_config('moashir.criteria_text_correction', 'on', true)");
        DB::table('main_criteria')->where('id', $mainId)->update(['name' => $name]);
    });

    expect(mainOf($published, 'M1')->name->en)->toBe('Leadership');
});

it('keeps frozen version attributes and the status flow intact', function (): void {
    $published = publishVersion(balancedDraft());

    expect(fn () => DB::transaction(fn () => DB::table('criteria_versions')->where('id', $published->id)->update(['scale_kind' => 'relative'])))->toThrow(QueryException::class, 'frozen');
    expect(fn () => DB::transaction(fn () => DB::table('criteria_versions')->where('id', $published->id)->delete()))->toThrow(QueryException::class, 'cannot be deleted');
    expect(fn () => DB::transaction(fn () => DB::table('criteria_versions')->where('id', $published->id)->update(['status' => 'draft'])))->toThrow(QueryException::class, 'cannot become');

    $archived = archiveVersion($published);
    expect(fn () => DB::transaction(fn () => DB::table('criteria_versions')->where('id', $archived->id)->update(['status' => 'published'])))->toThrow(QueryException::class, 'cannot become');
});

it('keeps draft versions freely editable', function (): void {
    $draft = balancedDraft();

    DB::table('main_criteria')->where('id', mainOf($draft, 'M1')->id)->update(['weight' => 900]);

    expect(mainOf($draft, 'M1')->weight->toDecimal())->toBe('900.00');
});

it('keeps the criteria edit events append-only', function (): void {
    $published = publishVersion(balancedDraft());
    $event = CriteriaEditEvent::query()->create([
        'kind' => CriteriaEditKind::TextCorrection,
        'scope' => CriteriaEditScope::All,
        'from_version_id' => $published->id,
        'changes' => [],
        'edited_by_user_id' => criteriaOfficer()->id,
    ]);

    expect(fn () => $event->forceFill(['reason' => 'تعديل غير مسموح.'])->save())->toThrow(AppendOnlyViolation::class)
        ->and(fn () => $event->delete())->toThrow(AppendOnlyViolation::class)
        ->and(fn () => DB::transaction(fn () => DB::table('criteria_edit_events')->where('id', $event->id)->update(['scope' => 'new_only'])))->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::table('criteria_edit_events')->where('id', $event->id)->delete()))->toThrow(QueryException::class, 'append_only');
});

it('refuses a text correction that claims another scope', function (): void {
    $published = publishVersion(balancedDraft());
    $editorId = criteriaOfficer()->id;

    expect(fn () => DB::transaction(fn () => DB::table('criteria_edit_events')->insert([
        'kind' => 'text_correction',
        'scope' => 'reset_open',
        'from_version_id' => $published->id,
        'changes' => '[]',
        'edited_by_user_id' => $editorId,
        'created_at' => now(),
    ])))->toThrow(QueryException::class, 'criteria_edit_events_text_scope_check');
});

it('allows only one published version and one open draft', function (): void {
    publishVersion(balancedDraft());
    $second = draftVersion();

    expect(fn () => DB::transaction(fn (): CriteriaVersion => publishVersion(draftVersion())))->toThrow(QueryException::class, 'criteria_versions_one')
        ->and($second->exists)->toBeTrue();
});
