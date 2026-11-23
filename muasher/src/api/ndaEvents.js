// The staff agreement gate (operator decision, 2026-11-22): a 403 `nda.acceptance_required` asks the agreement
// modal to open, and the requests waiting on it resume once the user has accepted.
const listeners = new Set()
let pending = null

export function onNdaRequired(listener) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

// One shared wait for every refused request, so the modal opens once however many requests were refused.
export function requestNdaAcceptance() {
  if (!pending) {
    let resolve
    const promise = new Promise((done) => {
      resolve = done
    })
    pending = { promise, resolve }
    listeners.forEach((listener) => listener())
  }
  return pending.promise
}

export function markNdaAccepted() {
  pending?.resolve()
  pending = null
}

export function resetNdaGate() {
  pending = null
}
