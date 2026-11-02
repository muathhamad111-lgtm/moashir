const counters = new Set()
const listeners = new Set()

export function registerOpenAssessmentCounter(counter) {
  counters.add(counter)
}

export function registerCriteriaEditListener(listener) {
  listeners.add(listener)
}

export function openAssessmentsBoundTo(versionId) {
  return [...counters].reduce((total, counter) => total + counter(versionId), 0)
}

export function announceCriteriaEdit(event) {
  listeners.forEach((listener) => listener(event))
}
