import { findCycle } from './cycleStore'
import { awardReject } from './awardRejection'

// An advanced request's completion deadline falls before the committee starts, whoever asks for it.
export function assertBeforeCommittee(record, deadlineAt) {
  const cycle = findCycle(record.awardCycleId)
  if (new Date(deadlineAt).getTime() > new Date(cycle.committeeStart).getTime())
    throw awardReject.deadlineAfterCommittee(cycle.committeeStart)
}
