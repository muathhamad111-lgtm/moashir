import { boundTree } from '../assessments/assessmentsStore'
import {
  decimalRaw,
  displayPercent,
  rawPercent,
  scorableUnits,
  unitKey,
} from '../assessments/selfScoring'
import { completionResource } from './accreditedSerializers'
import { boundMainsOf, boundSubsOf } from './accreditedRecords'

// Mirrors the API's completion rounds (design §B.3.3, decision UX-2): each score version carries
// the completion request whose resubmission opened its round (null for the first evaluation).
const RESOLVED_ROUND_STATUSES = ['resubmitted', 'expired-continued', 'expired-declined']
const FIELD_PREFIX = { sub: 'sub', point: 'point' }

function requestsOf(record) {
  return [...(record.completionRequests ?? [])].sort((a, b) => (a.createdAt < b.createdAt ? -1 : 1))
}

export function currentRoundId(record) {
  const resolved = requestsOf(record).filter(
    (request) => request.resolvedAt && RESOLVED_ROUND_STATUSES.includes(request.status),
  )
  return resolved.at(-1)?.id ?? null
}

// The units the entity was asked to complete in the round now under review, still without a
// result from that round (the API's CurrentRoundRescoreCheck). Only a resubmission asks for one.
export function unitsAwaitingRescore(record) {
  const opener = requestsOf(record)
    .filter((request) => request.resolvedAt && RESOLVED_ROUND_STATUSES.includes(request.status))
    .at(-1)
  if (opener?.status !== 'resubmitted') return []
  const codes = new Set(opener.items.map((item) => item.subCode).filter(Boolean))
  return boundTree(record)
    .mains.flatMap((main) => main.subs.filter((sub) => codes.has(sub.code)))
    .flatMap((sub) => scorableUnits({ mains: [{ subs: [sub] }] }))
    .filter(
      (unit) => lastEntry(record, unitKey(unit.level, unit.id))?.completionRequestId !== opener.id,
    )
}

function historyOf(record) {
  record.scoreHistory ??= []
  return record.scoreHistory
}

function lastEntry(record, key) {
  return historyOf(record).findLast((entry) => entry.key === key) ?? null
}

// A saved value is a new version when it differs from the current one, or when the assessor
// confirms it again in a later round (the API's re-confirmation rule).
export function recordScoreVersions(record, changes, at) {
  const round = currentRoundId(record)
  changes.forEach(([key, raw]) => {
    const previous = record.scores[key]
    const last = lastEntry(record, key)
    const lastRound = last ? last.completionRequestId : null
    const confirmsInNewRound = round !== null && lastRound !== round
    if (previous === raw && !confirmsInNewRound) return
    if (previous !== undefined && !last) historyOf(record).push(seedEntry(record, key, previous))
    historyOf(record).push({ key, raw, completionRequestId: round, at })
  })
}

function seedEntry(record, key, raw) {
  return { key, raw, completionRequestId: null, at: record.submittedAt ?? record.createdAt }
}

function roundNumbers(record) {
  return new Map(requestsOf(record).map((request, index) => [request.id, index + 2]))
}

function entriesOf(record, key) {
  const history = historyOf(record).filter((entry) => entry.key === key)
  if (history.length) return history
  const raw = record.scores[key]
  return raw === undefined ? [] : [seedEntry(record, key, raw)]
}

function unitRounds(record, unit, numbers) {
  const lastByRound = new Map()
  entriesOf(record, unitKey(unit.level, unit.id)).forEach((entry) => {
    lastByRound.set(numbers.get(entry.completionRequestId) ?? 1, entry)
  })
  return [...lastByRound.entries()]
    .sort(([a], [b]) => a - b)
    .map(([round, entry]) => ({
      round,
      completionRequestId: entry.completionRequestId,
      rawValue: decimalRaw(entry.raw),
      percent: displayPercent(rawPercent(entry.raw, record.scaleKind)),
      scoredAt: entry.at,
    }))
}

export function roundsResource(record) {
  const numbers = roundNumbers(record)
  const subs = boundSubsOf(record)
  const mains = boundMainsOf(record)
  const units = Object.fromEntries(
    scorableUnits(boundTree(record))
      .map((unit) => [`${FIELD_PREFIX[unit.level]}-${unit.id}`, unitRounds(record, unit, numbers)])
      .filter(([, rounds]) => rounds.length),
  )
  return {
    requests: requestsOf(record).map((request) => ({
      ...completionResource(request, subs, mains),
      round: numbers.get(request.id),
    })),
    units,
  }
}
