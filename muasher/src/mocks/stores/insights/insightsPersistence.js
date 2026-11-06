const PERSISTED = import.meta.env.MODE !== 'test'

function storage() {
  return PERSISTED && typeof sessionStorage !== 'undefined' ? sessionStorage : null
}

export function restoredState(key) {
  const raw = storage()?.getItem(key)
  if (!raw) return null
  try {
    return JSON.parse(raw)
  } catch {
    return null
  }
}

export function persistState(key, value) {
  storage()?.setItem(key, JSON.stringify(value))
}

export function forgetState(key) {
  storage()?.removeItem(key)
}
