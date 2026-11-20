const listeners = new Set()
let lastServerActivity = Date.now()

export function onSessionExpired(listener) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

export function notifySessionExpired() {
  listeners.forEach((listener) => listener())
}

const identityListeners = new Set()

export function onIdentityRequired(listener) {
  identityListeners.add(listener)
  return () => identityListeners.delete(listener)
}

export function notifyIdentityRequired() {
  identityListeners.forEach((listener) => listener())
}

export function markServerActivity(at = Date.now()) {
  lastServerActivity = at
}

export function lastServerActivityAt() {
  return lastServerActivity
}
