export function toCertificateCard(raw = {}) {
  return {
    id: raw.id ?? null,
    number: raw.number ?? '',
    kind: raw.kind ?? null,
    level: raw.level ?? null,
    levelLabel: raw.levelLabel ?? null,
    status: raw.status ?? null,
    isTest: raw.isTest === true,
    percent: raw.percent ?? null,
    requestId: raw.request?.id ?? null,
    requestNumber: raw.request?.number ?? '',
    entityName: raw.entity?.name ?? null,
    verificationCode: raw.verificationCode ?? '',
    verificationUrl: raw.verificationUrl ?? '',
    issuedAt: raw.issuedAt ?? null,
    revokedAt: raw.revokedAt ?? null,
    revokedReason: raw.revokedReason ?? null,
    supersededAt: raw.supersededAt ?? null,
    suspendedAt: raw.suspendedAt ?? null,
    hasPdf: raw.hasPdf === true,
    validUntil: raw.validUntil ?? null,
    isExpired: raw.isExpired === true,
    validity: raw.validity ?? null,
    daysUntilExpiry: raw.daysUntilExpiry ?? null,
  }
}

export function toVerification(raw = {}) {
  return {
    result: raw.result ?? 'not_found',
    certificate: raw.certificate
      ? {
          number: raw.certificate.number ?? '',
          kind: raw.certificate.kind ?? null,
          level: raw.certificate.level ?? null,
          levelLabel: raw.certificate.levelLabel ?? null,
          percent: raw.certificate.percent ?? null,
          entityName: raw.certificate.entityName ?? null,
          issuedAt: raw.certificate.issuedAt ?? null,
        }
      : null,
  }
}

function toActor(raw) {
  return raw ? { id: raw.id ?? null, name: raw.name ?? '' } : null
}

export function toStaffCertificate(raw = {}) {
  return {
    ...toCertificateCard(raw),
    issuedBy: toActor(raw.issuedBy),
    revokedBy: toActor(raw.revokedBy),
    reissueReason: raw.reissueReason ?? null,
    replaces: raw.replaces
      ? { id: raw.replaces.id ?? null, number: raw.replaces.number ?? '' }
      : null,
  }
}
