import { assessmentSettings } from '../accredited/assessmentSettingsStore'
import { allCycles, isRunning, runningCycles } from './cycleStore'
import { awardReject } from './awardRejection'

const SINGLE = 'single'
const STAGGERED = 'staggered'
const SUBMISSION_OPEN = 'submission-open'

export function cycleConcurrency() {
  return assessmentSettings().advancedCycleConcurrency ?? SINGLE
}

function blocks(mode, target, cycle) {
  if (mode === SINGLE) return isRunning(cycle)
  if (mode === STAGGERED) return target === SUBMISSION_OPEN && cycle.status === SUBMISSION_OPEN
  return false
}

export function openBlockOf(cycle, target = SUBMISSION_OPEN) {
  const mode = cycleConcurrency()
  const blocking = runningCycles().find(
    (other) => other.id !== cycle.id && blocks(mode, target, other),
  )
  if (!blocking) return null
  return {
    code: mode === SINGLE ? 'cycle.another_open' : 'cycle.another_open_for_submission',
    concurrency: mode,
    cycle: {
      id: blocking.id,
      name: { ...blocking.name },
      year: blocking.year,
      status: blocking.status,
    },
  }
}

export function runningCycleCounts() {
  return {
    running: runningCycles().length,
    openForSubmission: allCycles().filter((cycle) => cycle.status === SUBMISSION_OPEN).length,
  }
}

export function assertMayStart(cycle, target) {
  const block = openBlockOf(cycle, target)
  if (block) throw awardReject.startBlocked(block)
}
