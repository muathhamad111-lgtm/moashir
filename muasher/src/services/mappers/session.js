import { normalizedCodeChannels } from '@/utils/auth/codeChannels'

function roleKeyOf(role) {
  return typeof role === 'string' ? role : (role?.key ?? null)
}

function toEntitySummary(entity) {
  if (!entity) return null
  return {
    id: entity.id,
    reference: entity.reference ?? null,
    name: entity.name ?? null,
    status: entity.status ?? null,
    profileCompleted: entity.profileCompleted === true,
  }
}

function toRequestScope(raw) {
  if (!raw) return null
  return { allPaths: raw.allPaths === true, paths: [...(raw.paths ?? [])] }
}

export function toSessionUser(raw = {}) {
  const entity = toEntitySummary(raw.entity)
  return {
    id: raw.id ?? null,
    name: raw.name ?? '',
    email: raw.email ?? '',
    locale: raw.locale ?? null,
    theme: raw.theme ?? null,
    roles: (raw.roles ?? []).map(roleKeyOf).filter(Boolean),
    permissions: [...(raw.permissions ?? [])],
    isSuperAdmin: raw.isSuperAdmin === true,
    mfaEnabled: raw.mfaEnabled === true,
    passwordChangeRequired: raw.passwordChangeRequired === true,
    entityId: raw.entityId ?? entity?.id ?? null,
    entity,
    committeeAppointments: [...(raw.committeeAppointments ?? [])],
    assessorQualifications: [...(raw.assessorQualifications ?? [])],
    requestScope: toRequestScope(raw.requestScope),
    sessionExpiresAt: raw.sessionExpiresAt ?? null,
    idleTimeoutMinutes: raw.idleTimeoutMinutes ?? null,
    profileCompleted: entity ? entity.profileCompleted : true,
    status: entity?.status ?? raw.status ?? null,
  }
}

export function toLoginOutcome(raw = {}) {
  return {
    step: raw.step ?? null,
    user: raw.user ? toSessionUser(raw.user) : null,
    otp: raw.otp ?? null,
    entityId: raw.entityId ?? null,
    recoveryCodes: [...(raw.recoveryCodes ?? [])],
    message: raw.message ?? '',
  }
}

export function toLoginModes(raw = {}) {
  return {
    identifiers: [...(raw.identifiers ?? [])],
    codeChannels: normalizedCodeChannels(raw.codeChannels),
    otpLength: raw.otpLength ?? null,
    otpExpirySeconds: raw.otpExpirySeconds ?? null,
    resendCooldownSeconds: raw.resendCooldownSeconds ?? null,
  }
}

export function toMfaEnrollment(raw = {}) {
  return {
    qrSvg: raw.qrSvg ?? '',
    manualEntryKey: raw.manualEntryKey ?? '',
  }
}
