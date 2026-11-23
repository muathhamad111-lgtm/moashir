<?php

declare(strict_types=1);

use App\Enums\Identity\TermsKind;
use App\Models\Identity\TermsVersion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function ndaKindMigration(): object
{
    return require database_path('migrations/2026_11_22_230000_add_nda_kind_to_terms_versions_table.php');
}

function ndaAcceptedRolesMigration(): object
{
    return require database_path('migrations/2026_11_22_230100_add_accepted_roles_to_terms_acceptances_table.php');
}

function ndaRemindersMigration(): object
{
    return require database_path('migrations/2026_11_22_230200_create_nda_reminders_table.php');
}

it('refuses to roll back the agreement kind while agreement versions exist', function (): void {
    publishedNda();

    expect(fn () => ndaKindMigration()->down())->toThrow(RuntimeException::class);
});

it('rolls the agreement migrations back and forward when no agreement exists', function (): void {
    ndaRemindersMigration()->down();
    ndaAcceptedRolesMigration()->down();
    ndaKindMigration()->down();

    expect(fn () => DB::transaction(fn (): TermsVersion => legalDraft(TermsKind::Nda)))->toThrow(QueryException::class);

    ndaKindMigration()->up();
    ndaAcceptedRolesMigration()->up();
    ndaRemindersMigration()->up();

    expect(legalDraft(TermsKind::Nda)->kind)->toBe('nda');
});
