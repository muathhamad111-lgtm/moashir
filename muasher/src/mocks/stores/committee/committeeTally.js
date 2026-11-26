import { divideHalfUp, displayPercent } from '../assessments/selfScoring'
import { committeeState, findAverage, insert, liveRowsOfRecord } from './committeeState'

export function isPendingSubstitute(row) {
  return row.replacesAssignmentId !== null && row.substituteApprovedAt === null
}

export function countedRows(record) {
  return liveRowsOfRecord(record.id).filter((row) => !isPendingSubstitute(row))
}

export function pendingSubstitutes(record) {
  return liveRowsOfRecord(record.id).filter(isPendingSubstitute)
}

export function isComplete(record) {
  const counted = countedRows(record)
  return counted.length > 0 && counted.every((row) => row.status === 'completed')
}

function digestOf(rows) {
  return rows
    .map((row) => `${row.id}:${row.percent}`)
    .sort()
    .join('|')
}

function meanOf(rows) {
  const total = rows.reduce((sum, row) => sum + row.percent, 0)
  return divideHalfUp(total, rows.length)
}

export function currentAverage(record) {
  return record.committeeAverageId ? findAverage(record.committeeAverageId) : null
}

export function recordAverage(record, { isManual = false, at = new Date().toISOString() } = {}) {
  const rows = countedRows(record)
  const digest = digestOf(rows)
  const current = currentAverage(record)
  if (current && current.inputsDigest === digest) return { average: current, recomputed: false }
  const version =
    committeeState.averages.filter((average) => average.assessmentId === record.id).length + 1
  const average = insert('averages', {
    assessmentId: record.id,
    version,
    percent: displayPercent(meanOf(rows)),
    memberCount: rows.length,
    isManual,
    inputsDigest: digest,
    inputs: rows.map((row) => ({ assignmentId: row.id, percent: displayPercent(row.percent) })),
    createdAt: at,
  })
  record.committeeAverageId = average.id
  return { average, recomputed: true }
}

export function averageResource(average) {
  if (!average) return null
  return {
    id: average.id,
    version: average.version,
    percent: average.percent,
    memberCount: average.memberCount,
    isManual: average.isManual,
    computedAt: average.createdAt,
  }
}
