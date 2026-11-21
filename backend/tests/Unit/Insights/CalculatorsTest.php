<?php

declare(strict_types=1);

use App\Data\Assessments\FeedbackSheet;
use App\Data\Insights\ClassifiedSub;
use App\Data\Insights\QuotedItem;
use App\Data\Insights\ResultMain;
use App\Enums\Insights\DeltaComparison;
use App\Enums\Insights\MaturityBand;
use App\Support\Insights\ExecutiveSummaryBuilder;
use App\Support\Insights\GapClassifier;
use App\Support\Insights\MaturityBandClassifier;
use App\Support\Insights\ResultDeltaCalculator;
use App\Support\Insights\TextExcerpt;
use App\Support\ValueObjects\GapThresholds;
use App\Support\ValueObjects\MaturityBounds;

it('puts every sub-criterion in exactly one column and orders each column', function (): void {
    $tree = resultTree([
        'M1' => ['M1.1' => ['100.00', '40.00'], 'M1.2' => ['20.00', '10.00'], 'M1.3' => ['50.00', '60.00']],
        'M2' => ['M2.1' => ['100.00', '60.00'], 'M2.2' => ['10.00', '90.00'], 'M2.3' => ['30.00', '90.00']],
    ]);

    $classes = new GapClassifier()->classify($tree, GapThresholds::defaults());
    $codes = static fn (array $items): array => array_map(static fn (ClassifiedSub $item): string => $item->sub->code, $items);

    expect($codes($classes->criticalGaps))->toBe(['M1.1', 'M1.2'])
        ->and($codes($classes->opportunities))->toBe(['M2.1', 'M1.3'])
        ->and($codes($classes->strengths))->toBe(['M2.3', 'M2.2'])
        ->and($classes->counts())->toBe(['strength' => 2, 'opportunity' => 2, 'critical_gap' => 2])
        ->and(count($classes->mains))->toBe(2);
});

it('breaks ties by percent, then by criteria order, and leaves empty columns empty', function (): void {
    $tree = resultTree(['M1' => ['M1.1' => ['50.00', '60.00'], 'M1.2' => ['40.00', '50.00']], 'M2' => ['M2.1' => ['50.00', '60.00']]]);

    $classes = new GapClassifier()->classify($tree, GapThresholds::defaults());

    expect(array_map(static fn (ClassifiedSub $item): string => $item->sub->code, $classes->opportunities))->toBe(['M1.2', 'M1.1', 'M2.1'])
        ->and($classes->strengths)->toBe([])
        ->and($classes->criticalGaps)->toBe([]);
});

it('bands the mains, the subs and the total', function (): void {
    $tree = resultTree(['M1' => ['M1.1' => ['50.00', '85.00'], 'M1.2' => ['50.00', '45.00']], 'M2' => ['M2.1' => ['100.00', '72.00']]]);

    $profile = new MaturityBandClassifier()->profileOf($tree, MaturityBounds::defaults());

    expect($profile->overall)->toBe(MaturityBand::Developing)
        ->and($profile->mains[0]->band)->toBe(MaturityBand::Developing)
        ->and($profile->mains[0]->subs[0]->band)->toBe(MaturityBand::Leading)
        ->and($profile->mains[0]->subs[1]->band)->toBe(MaturityBand::Emerging)
        ->and($profile->mains[1]->band)->toBe(MaturityBand::Mature)
        ->and($profile->mainCounts())->toBe(['emerging' => 0, 'developing' => 1, 'mature' => 1, 'leading' => 0]);
});

it('matches mains and subs by code, labels new and removed criteria and flags different versions', function (): void {
    $previous = resultTree(['M1' => ['M1.1' => ['50.00', '50.00'], 'M1.2' => ['50.00', '50.00']], 'M2' => ['M2.1' => ['100.00', '70.00']]], 1);
    $current = resultTree(['M1' => ['M1.1' => ['50.00', '60.00'], 'M1.3' => ['50.00', '80.00']], 'M3' => ['M3.1' => ['100.00', '90.00']]], 2);

    $delta = new ResultDeltaCalculator()->compare($current, $previous);

    expect($delta->versionsDiffer)->toBeTrue()
        ->and($delta->total?->toDisplay())->toBe('20.00')
        ->and($delta->forMain('M1')->comparison)->toBe(DeltaComparison::Matched)
        ->and($delta->forMain('M1')->delta?->toDisplay())->toBe('20.00')
        ->and($delta->forMain('M1')->forSub('M1.1')?->toDisplay())->toBe('10.00')
        ->and($delta->forMain('M1')->forSub('M1.3'))->toBeNull()
        ->and($delta->forMain('M3')->comparison)->toBe(DeltaComparison::NewInVersion)
        ->and(array_map(static fn (ResultMain $main): string => $main->code, $delta->removed))->toBe(['M2']);
});

it('has no delta without a previous result', function (): void {
    $delta = new ResultDeltaCalculator()->compare(resultTree(['M1' => ['M1.1' => ['10.00', '50.00']]]), null);

    expect($delta->hasPrevious())->toBeFalse()
        ->and($delta->total)->toBeNull()
        ->and($delta->forMain('M1')->comparison)->toBe(DeltaComparison::NoPrevious);
});

it('picks the top three strengths and priority gaps, critical gaps before opportunities, with the assessor texts', function (): void {
    $tree = resultTree(['M1' => [
        'M1.1' => ['10.00', '95.00'], 'M1.2' => ['10.00', '90.00'], 'M1.3' => ['10.00', '85.00'], 'M1.4' => ['10.00', '81.00'],
        'M1.5' => ['10.00', '75.00'], 'M1.6' => ['100.00', '60.00'], 'M1.7' => ['10.00', '30.00'],
    ]]);
    $classes = new GapClassifier()->classify($tree, GapThresholds::defaults());
    $feedback = new FeedbackSheet(['M1.1' => ['strength' => 'نص القوة', 'improvement' => 'نص التحسين'], 'M1.7' => ['improvement' => 'أولوية']]);

    $highlights = new ExecutiveSummaryBuilder()->highlights($classes, $feedback);

    expect(array_map(static fn (QuotedItem $item): string => $item->item->sub->code, $highlights->strongAreas))->toBe(['M1.1', 'M1.2', 'M1.3'])
        ->and($highlights->strongAreas[0]->quote)->toBe('نص القوة')
        ->and($highlights->strongAreas[1]->quote)->toBeNull()
        ->and(array_map(static fn (QuotedItem $item): string => $item->item->sub->code, $highlights->priorityGaps))->toBe(['M1.7', 'M1.6', 'M1.5'])
        ->and($highlights->priorityGaps[0]->quote)->toBe('أولوية');
});

it('cuts long texts at a word boundary and keeps short texts whole', function (): void {
    $long = TextExcerpt::of('كلمة أولى ثانية ثالثة رابعة', 14);
    $short = TextExcerpt::of('  نص قصير  ', 600);
    $single = TextExcerpt::of(str_repeat('ب', 30), 10);

    expect($long->text)->toBe('كلمة أولى')
        ->and($long->isTruncated)->toBeTrue()
        ->and($short->text)->toBe('نص قصير')
        ->and($short->isTruncated)->toBeFalse()
        ->and(mb_strlen($single->text))->toBe(10);
});
