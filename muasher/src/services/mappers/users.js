function toRequestScope(raw) {
  return { allPaths: raw?.allPaths !== false, paths: [...(raw?.paths ?? [])] }
}

export function toUser(raw = {}) {
  return {
    id: raw.id ?? null,
    name: raw.name ?? '',
    email: raw.email ?? '',
    phone: raw.phone ?? null,
    roles: raw.roles ?? [],
    assessorQualifications: [...(raw.assessorQualifications ?? [])],
    requestScope: toRequestScope(raw.requestScope),
    status: raw.status ?? null,
    mfaEnabled: raw.mfaEnabled === true,
    lastLoginAt: raw.lastLoginAt ?? null,
    entityId: raw.entityId ?? null,
    createdAt: raw.createdAt ?? null,
  }
}

export function toUserStats(raw) {
  if (!raw) return null
  return {
    total: raw.total ?? 0,
    active: raw.active ?? 0,
    locked: raw.locked ?? 0,
    suspended: raw.suspended ?? 0,
    invited: raw.invited ?? 0,
    byRole: raw.byRole ?? {},
  }
}
