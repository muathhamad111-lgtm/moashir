import { allAssessments, isOpen } from '../assessments/assessmentsStore'
import { allCycles, runningCycles } from './cycleStore'

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
  const running = runningCycles().map(bindingOf)
  return {
    running: running[0] ?? null,
    runningCycles: running,
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
