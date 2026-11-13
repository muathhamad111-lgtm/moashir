<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Settings\SettingKey;
use App\Models\Assessments\Assessment;
use App\Models\Certificates\Certificate;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Services\Settings\SettingsStore;

const CERTIFICATE_REASON = 'خطأ مطبعي في اسم الجهة بعد تحديث بياناتها الرسمية لدى المركز الوطني.';

/**
 * @return array{assessment: Assessment, certificate: Certificate, assessor: User}
 */
function certifiedAccreditedRequest(?CriteriaVersion $version = null): array
{
    $assessor = assessorUser();
    $assessment = completedAccreditedRequest($version ?? publishedAccreditedCriteria(), $assessor);

    return [
        'assessment' => $assessment,
        'certificate' => Certificate::query()->where('assessment_id', $assessment->id)->sole(),
        'assessor' => $assessor,
    ];
}

/**
 * @param  array<string, string>  $thresholds
 */
function setLevelThresholds(array $thresholds): void
{
    app(SettingsStore::class)->put(SettingKey::CertificateLevelThresholds, $thresholds, AuditEvent::CertificateSettingsUpdated, null);
}

function setAccreditedCertificateIssuance(bool $enabled): void
{
    app(SettingsStore::class)->put(SettingKey::AccreditedCertificateIssuance, $enabled, AuditEvent::CertificateSettingsUpdated, null);
}
