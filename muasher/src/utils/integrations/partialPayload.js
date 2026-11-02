import { CREDENTIAL_SOURCES, NO_ACTIVE_PROVIDER } from '@/constants/integrations'

function isPlainObject(value) {
  return Object.prototype.toString.call(value) === '[object Object]'
}

export function isSameValue(left, right) {
  return JSON.stringify(left ?? null) === JSON.stringify(right ?? null)
}

export function writesEverything(resource) {
  return resource?.source === CREDENTIAL_SOURCES.ENVIRONMENT
}

export function changedValues(next, current, { full = false } = {}) {
  return Object.fromEntries(Object.entries(next).filter(([key, value]) => full || !isSameValue(value, current?.[key])))
}

export function withoutEmptyObjects(payload) {
  return Object.fromEntries(Object.entries(payload).filter(([, value]) => !isPlainObject(value) || Object.keys(value).length > 0))
}

export function trimmedOrNull(value) {
  const text = String(value ?? '').trim()
  return text === '' ? null : text
}

export function activeProviderValue(choice) {
  return choice === NO_ACTIVE_PROVIDER ? null : choice
}

export function activeProviderChoice(provider) {
  return provider ?? NO_ACTIVE_PROVIDER
}
