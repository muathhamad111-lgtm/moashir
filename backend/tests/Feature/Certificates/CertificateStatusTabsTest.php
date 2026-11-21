<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentPath;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Models\Identity\UserRequestPath;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @return array{owner: User, current: string, previous: string}
 */
function reissuedCertificateOwner(CriteriaVersion $version): array
{
    ['assessment' => $assessment, 'certificate' => $certificate] = certifiedAccreditedRequest($version);
    $current = switchTo(systemAdmin())->postJson("/api/v1/certificates/{$certificate->public_id}/reissue", ['reason' => CERTIFICATE_REASON])->assertCreated()->json('data.id');

    return ['owner' => ownerOf($assessment->entity), 'current' => (string) $current, 'previous' => $certificate->public_id];
}

it('groups the own certificates by a list of statuses and counts every status regardless of that filter', function (): void {
    $version = publishedAccreditedCriteria();
    ['owner' => $owner, 'current' => $current, 'previous' => $previous] = reissuedCertificateOwner($version);
    certifiedAccreditedRequest($version);
    $counts = ['issued' => 1, 'suspended' => 0, 'revoked' => 0, 'superseded' => 1];

    switchTo($owner)->getJson('/api/v1/certificates?filter[status]=issued,suspended')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $current)
        ->assertJsonPath('meta.status_counts', $counts);
    switchTo($owner)->getJson('/api/v1/certificates?filter[status]=revoked,superseded')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $previous)
        ->assertJsonPath('meta.status_counts', $counts);
});

it('counts the certificates within the record scope of the staff viewer', function (): void {
    $version = publishedAccreditedCriteria();
    reissuedCertificateOwner($version);
    certifiedAccreditedRequest($version);
    $advancedOnly = systemAdmin();
    UserRequestPath::query()->create(['user_id' => $advancedOnly->id, 'path' => AssessmentPath::Advanced]);

    switchTo(systemAdmin())->getJson('/api/v1/certificates?filter[status]=issued')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.status_counts', ['issued' => 2, 'suspended' => 0, 'revoked' => 0, 'superseded' => 1]);
    switchTo($advancedOnly->refresh())->getJson('/api/v1/certificates')->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.status_counts', ['issued' => 0, 'suspended' => 0, 'revoked' => 0, 'superseded' => 0]);
});

it('refuses an unknown certificate status in the list filter', function (): void {
    switchTo(systemAdmin())->getJson('/api/v1/certificates?filter[status]=issued,lost')->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['filter.status']);
});
