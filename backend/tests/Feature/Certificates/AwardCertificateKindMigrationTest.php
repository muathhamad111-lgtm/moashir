<?php

declare(strict_types=1);

use App\Models\Certificates\Certificate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

function awardCertificateKindMigration(): Migration
{
    return require database_path('migrations/2026_11_22_210000_allow_award_certificate_kinds.php');
}

function certificateKindCheck(): string
{
    return (string) DB::scalar("SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conname = 'certificates_kind_check'");
}

it('narrows the certificate kinds back to accredited only while no award certificate exists, and widens them again', function (): void {
    $migration = awardCertificateKindMigration();

    $migration->down();
    expect(certificateKindCheck())->toContain('accredited')->not->toContain('advanced_winner');

    $migration->up();
    expect(certificateKindCheck())->toContain('advanced_winner')->toContain('advanced_qualified')->toContain('advanced_participation');
});

it('refuses to narrow the certificate kinds once an award certificate exists', function (): void {
    Notification::fake();
    $version = publishedAccreditedCriteria();
    initiallyAssessedRequest(openAwardCycle($version, ['participation_certificate_enabled' => true]), $version, assessorUser());
    expect(Certificate::query()->where('kind', 'advanced_participation')->exists())->toBeTrue();

    expect(fn () => awardCertificateKindMigration()->down())->toThrow(RuntimeException::class, 'Award certificates exist');
});
