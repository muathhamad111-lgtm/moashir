// Whether assigning and reassigning a request need a reason (general settings, admin review 2026-11-20).
const DEFAULTS = Object.freeze({ assignRequired: false, reassignRequired: true })
const state = { ...DEFAULTS }

export function assignmentReasonRules() {
  return { ...state }
}

export function saveAssignmentReasonRules({ assignRequired, reassignRequired }) {
  state.assignRequired = assignRequired
  state.reassignRequired = reassignRequired
  return assignmentReasonRules()
}

export function resetAssignmentReasonRules() {
  Object.assign(state, DEFAULTS)
}
