import { toLegalVersion } from '@/services/legalVersionsService'

const EMPTY_BLOCKS = Object.freeze({ ar: [], en: [] })

function localized(raw) {
  return raw ? { ar: raw.ar ?? '', en: raw.en ?? '' } : null
}

// The agreement as the modal and the invitation step read it: parsed blocks, never HTML.
export function toNdaText(raw) {
  if (!raw) return null
  return {
    id: raw.id ?? null,
    version: raw.version ?? '',
    title: localized(raw.title),
    publishedAt: raw.publishedAt ?? null,
    body: raw.body ?? EMPTY_BLOCKS,
    changeSummary: raw.changeSummary ?? null,
  }
}

export function toNdaVersion(raw = {}) {
  return {
    ...toLegalVersion(raw),
    changeSummary: localized(raw.changeSummary),
    acceptancesCount: raw.acceptancesCount ?? null,
    publishedBy: raw.publishedBy ?? null,
  }
}

export function toNdaOverview(raw = {}) {
  return {
    current: raw.current ? toNdaVersion(raw.current) : null,
    draft: raw.draft ? toNdaVersion(raw.draft) : null,
    roles: [...(raw.roles ?? [])],
    availableRoles: [...(raw.availableRoles ?? [])],
    reminderDays: raw.reminderDays ?? 3,
    maxReminderDays: raw.maxReminderDays ?? 30,
    nextManualReminderAt: raw.nextManualReminderAt ?? null,
  }
}

export function toNdaAcceptance(raw = {}) {
  return {
    id: raw.id ?? null,
    termsVersionId: raw.termsVersionId ?? null,
    version: raw.version ?? '',
    acceptedAt: raw.acceptedAt ?? null,
    acceptedRoles: [...(raw.acceptedRoles ?? [])],
    ipMasked: raw.ipMasked ?? null,
  }
}

function toNdaRegisterUser(raw = {}) {
  return {
    id: raw.id ?? null,
    name: raw.name ?? '',
    email: raw.email ?? '',
    roles: [...(raw.roles ?? [])],
  }
}

export function toNdaRegisterRow(raw = {}) {
  return {
    user: toNdaRegisterUser(raw.user),
    status: raw.status ?? 'pending',
    lastAcceptedVersion: raw.lastAcceptedVersion ?? null,
    acceptedAt: raw.acceptedAt ?? null,
    acceptedRoles: [...(raw.acceptedRoles ?? [])],
    ipMasked: raw.ipMasked ?? null,
  }
}

export function toNdaRegisterSummary(raw = {}) {
  return { inScope: raw.inScope ?? 0, accepted: raw.accepted ?? 0, pending: raw.pending ?? 0 }
}
