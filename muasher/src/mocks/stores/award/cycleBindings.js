import { allAssessments, isOpen } from '../assessments/assessmentsStore'
import { allCycles, runningCycle } from './cycleStore'

export const DRAFT_CYCLE_CHOICES = ['keep', 'move']

function bindingOf(cycle) {
  const openRequests = allAssessments().filter(
    (record) => record.awardCycleId === cycle.id && isOpen(record),
  ).length
  return {
    id: cycle.id,
    name: { ...cycle.name },
    status: cycle.status,
    criteriaVersionId: cycle.criteriaVersionId,
    openRequests,
  }
}

function draftCycles() {
  return allCycles().filter((cycle) => cycle.status === 'draft')
}

export function cycleBindingsSummary() {
  const running = runningCycle()
  return {
    running: running ? bindingOf(running) : null,
    requestsStayOnCycleVersion: true,
    drafts: draftCycles().map(bindingOf),
  }
}

export function moveDraftCycles(versionId) {
  return draftCycles().map((cycle) => {
    Object.assign(cycle, { criteriaVersionId: versionId, updatedAt: new Date().toISOString() })
    return cycle.id
  })
}
