const listeners = new Set()
let lastServerActivity = Date.now()

export function onSessionExpired(listener) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

export function notifySessionExpired() {
  listeners.forEach((listener) => listener())
}

export function markServerActivity(at = Date.now()) {
  lastServerActivity = at
}

export function lastServerActivityAt() {
  return lastServerActivity
}
