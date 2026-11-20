import { boolean, object } from 'yup'

const FIELDS = ['assignRequired', 'reassignRequired']

export function assignmentReasonsSchema() {
  return object({ assignRequired: boolean(), reassignRequired: boolean() })
}

export function assignmentReasonsValues(settings) {
  return {
    assignRequired: settings?.assignRequired === true,
    reassignRequired: settings?.reassignRequired !== false,
  }
}

// Both switches travel together: the API saves the pair, so any change sends both.
export function changedAssignmentReasons(settings, values) {
  const changed = FIELDS.some((field) => Boolean(values[field]) !== Boolean(settings?.[field]))
  return changed
    ? {
        assignRequired: Boolean(values.assignRequired),
        reassignRequired: Boolean(values.reassignRequired),
      }
    : {}
}
