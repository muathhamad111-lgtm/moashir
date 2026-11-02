const listeners = new Set()

export function onSessionExpired(listener) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

export function notifySessionExpired() {
  listeners.forEach((listener) => listener())
}
