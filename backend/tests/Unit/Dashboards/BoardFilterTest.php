<?php

declare(strict_types=1);

use App\Data\Dashboards\BoardFilter;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Entities\DevelopmentSector;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;

it('maps each path to the purpose of its payments', function (AssessmentPath $path, PaymentPurpose $purpose): void {
    expect(new BoardFilter(path: $path)->paymentPurpose())->toBe($purpose)
        ->and(PaymentPurpose::forPath($path))->toBe($purpose);
})->with([
    [AssessmentPath::SelfAssessment, PaymentPurpose::SelfAssessment],
    [AssessmentPath::Accredited, PaymentPurpose::Accredited],
    [AssessmentPath::Advanced, PaymentPurpose::Award],
]);

it('has no purpose and no entity dimensions when empty', function (): void {
    $filter = new BoardFilter;

    expect($filter->paymentPurpose())->toBeNull()
        ->and($filter->hasEntityDimensions())->toBeFalse()
        ->and(new BoardFilter(path: AssessmentPath::Accredited)->hasEntityDimensions())->toBeFalse()
        ->and(new BoardFilter(size: OrgSize::Small)->hasEntityDimensions())->toBeTrue();
});

it('keeps only the region when narrowed to it', function (): void {
    $narrowed = new BoardFilter('riyadh', AssessmentPath::Advanced, EntityType::Waqf, DevelopmentSector::Health, OrgSize::Large)->regionOnly();

    expect($narrowed)->toEqual(new BoardFilter(region: 'riyadh'));
});

it('applies the entity dimensions to entity columns', function (): void {
    $query = Entity::query();
    new BoardFilter('riyadh', AssessmentPath::Accredited, EntityType::Waqf, DevelopmentSector::Health, OrgSize::Large)->applyToEntities($query);

    expect($query->toSql())->toContain('"entities"."region_id" in (select "id" from "regions" where "code" = ?)')
        ->toContain('"entities"."entity_type" = ?')
        ->toContain('"entities"."development_sector" = ?')
        ->toContain('"entities"."org_size" = ?')
        ->not->toContain('path')
        ->and($query->getBindings())->toBe(['riyadh', 'waqf', 'health', 'large']);
});

it('leaves assessments and payments untouched without a filter', function (): void {
    $assessments = Assessment::query();
    $payments = Payment::query();
    (new BoardFilter)->applyToAssessments($assessments);
    (new BoardFilter)->applyToPayments($payments);

    expect($assessments->getQuery()->wheres)->toBe([])
        ->and($payments->getQuery()->wheres)->toBe([]);
});

it('narrows assessments by path and payments by purpose through their entity', function (): void {
    $filter = new BoardFilter(path: AssessmentPath::Advanced, type: EntityType::CivilAssociation);
    $assessments = Assessment::query();
    $payments = Payment::query();
    $filter->applyToAssessments($assessments);
    $filter->applyToPayments($payments);

    expect($assessments->toSql())->toContain('"assessments"."entity_id" in (select "entities"."id" from "entities"')
        ->toContain('"assessments"."path" = ?')
        ->and($assessments->getBindings())->toBe(['civil_association', 'advanced'])
        ->and($payments->toSql())->toContain('"payments"."purpose" = ?')
        ->and($payments->getBindings())->toBe(['civil_association', 'award']);
});
