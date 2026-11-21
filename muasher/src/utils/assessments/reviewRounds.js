import { COMPLETION_STATUSES } from '@/constants/accredited'
import { scoreFieldName } from './scoreSheet'

// Completion rounds in the review (design §B.3.3): round 1 is the evaluation before any completion;
// each request the entity resubmitted opens the next round. An item the entity was asked to complete
// shows one row per round, and waits for a new result until the assessor scores it in the current
// round.

function resubmitted(rounds) {
  return (rounds?.requests ?? []).filter(
    (request) => request.status === COMPLETION_STATUSES.RESUBMITTED && request.resolvedAt,
  )
}

// The request whose resubmission opened the round the assessor is scoring now.
export function currentRoundRequest(rounds) {
  return resubmitted(rounds).at(-1) ?? null
}

function itemOf(request, subCode) {
  return request?.items.find((item) => item.code === subCode) ?? null
}

function requestOfRound(rounds, round) {
  return (rounds?.requests ?? []).find((request) => request.round === round) ?? null
}

function wasRequested(rounds, subCode) {
  return resubmitted(rounds).some((request) => itemOf(request, subCode))
}

function entriesOf(rounds, unit) {
  return rounds?.units?.[scoreFieldName(unit)] ?? []
}

// The unit waits for a new result: the current round asked about its sub-criterion and the unit
// has no score from this round yet.
export function isAwaitingRescore(rounds, unit, subCode) {
  const request = currentRoundRequest(rounds)
  if (!request || !itemOf(request, subCode)) return false
  return !entriesOf(rounds, unit).some((entry) => entry.round === request.round)
}

// One row per round for an item that was part of a completion: its result, and the note the
// assessor sent with the request that followed it. The current round's row is pending until scored.
export function roundRowsOf(rounds, unit, subCode) {
  if (!wasRequested(rounds, subCode)) return []
  const entries = entriesOf(rounds, unit)
  const current = currentRoundRequest(rounds)
  const last = Math.max(current?.round ?? 1, ...entries.map((entry) => entry.round))
  return Array.from({ length: last }, (_, index) => index + 1).map((round) => {
    const entry = entries.find((candidate) => candidate.round === round) ?? null
    const next = requestOfRound(rounds, round + 1)
    return {
      round,
      entry,
      note: itemOf(next, subCode)?.note ?? null,
      pending: !entry && round === current?.round,
    }
  })
}

// Files the entity added after the current round's request was sent are marked «جديد».
export function isNewInRound(rounds, file) {
  const request = currentRoundRequest(rounds)
  if (!request?.requestedAt || !file.uploadedAt) return false
  return Date.parse(file.uploadedAt) > Date.parse(request.requestedAt)
}
