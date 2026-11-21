import { toCamelKey } from '@/api/caseMapping'

export function profileFieldName(key) {
  return String(key ?? '')
    .split('.')
    .map(toCamelKey)
    .join('.')
}

function toSectionCatalog(raw = {}) {
  return {
    key: raw.key,
    revision: raw.revision ?? '',
    fields: (raw.fields ?? []).map((field) => ({
      name: profileFieldName(field.key),
      requirement: field.requirement,
    })),
  }
}

function toCompletionSection(raw = {}) {
  return {
    key: raw.key,
    state: raw.state,
    missingCount: raw.missingCount ?? 0,
    missingFields: (raw.missingFields ?? []).map(profileFieldName),
  }
}

function toCompletion(raw = {}) {
  return {
    percent: raw.percent ?? 0,
    counted: raw.counted ?? 0,
    completedCount: raw.completedCount ?? 0,
    sections: (raw.sections ?? []).map(toCompletionSection),
  }
}

export function toOwnEntity(raw) {
  if (!raw) return raw
  return {
    ...raw,
    canEdit: raw.canEdit === true,
    profileSections: (raw.profileSections ?? []).map(toSectionCatalog),
    profileCompletion: toCompletion(raw.profileCompletion ?? {}),
  }
}

export function withOwnEntity(envelope) {
  return { ...envelope, data: toOwnEntity(envelope.data) }
}
