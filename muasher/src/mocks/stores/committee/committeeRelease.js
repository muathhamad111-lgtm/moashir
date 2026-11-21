import { allCycles, findCycle } from '../award/cycleStore'
import { activeSeatsOf, liveRowsOfRecord } from './committeeState'
import {
  cancelOpenDiscussion,
  distributeCycle,
  distributeRecord,
  isCommitteeRunning,
  syncCycleStage,
  voidRows,
} from './committeeLifecycle'
import { announceDistributed } from './committeeNotices'

export function releaseCommitteeWork(record, at) {
  voidRows(liveRowsOfRecord(record.id), 'withdrawn', at)
  cancelOpenDiscussion(record, at)
  record.committeeAverageId = null
  const cycle = findCycle(record.awardCycleId)
  if (cycle) syncCycleStage(cycle)
}

export function distributeQualified(cycle, record) {
  if (!isCommitteeRunning(cycle)) return
  const created = distributeRecord(cycle, record)
  if (created.length) announceDistributed(cycle, created)
  syncCycleStage(cycle)
}

export function startDueCommittees(now = Date.now()) {
  allCycles()
    .filter(
      (cycle) =>
        cycle.status === 'submission-closed' &&
        Date.parse(cycle.committeeStart) <= now &&
        activeSeatsOf(cycle.id).length,
    )
    .forEach((cycle) => {
      Object.assign(cycle, {
        status: 'committee-assessment',
        committeeStartedAt: new Date(now).toISOString(),
      })
      distributeCycle(cycle)
      syncCycleStage(cycle)
    })
}
