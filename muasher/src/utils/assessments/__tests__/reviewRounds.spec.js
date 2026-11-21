import { describe, expect, it } from 'vitest'
import {
  currentRoundRequest,
  isAwaitingRescore,
  isNewInRound,
  roundRowsOf,
} from '@/utils/assessments/reviewRounds'

const POINT = { level: 'point', id: 31 }
const OTHER = { level: 'point', id: 32 }

function request(overrides) {
  return {
    id: 7,
    round: 2,
    status: 'resubmitted',
    requestedAt: '2026-10-27T09:00:00+00:00',
    resolvedAt: '2026-11-12T10:00:00+00:00',
    items: [{ code: 'M1.1', kind: 'evidence', note: 'Attach the approved plan.' }],
    ...overrides,
  }
}

const BEFORE = { round: 1, rawValue: '3.00', percent: '30.00', scoredAt: '2026-10-26T08:00:00Z' }
const AFTER = { round: 2, rawValue: '8.00', percent: '80.00', scoredAt: '2026-11-13T08:00:00Z' }

describe('review completion rounds', () => {
  it('waits for a new result on an item the resubmitted request asked about', () => {
    const rounds = { requests: [request()], units: { 'point-31': [BEFORE] } }
    expect(currentRoundRequest(rounds).id).toBe(7)
    expect(isAwaitingRescore(rounds, POINT, 'M1.1')).toBe(true)
    expect(isAwaitingRescore(rounds, OTHER, 'M1.2')).toBe(false)
    expect(roundRowsOf(rounds, POINT, 'M1.1')).toEqual([
      { round: 1, entry: BEFORE, note: 'Attach the approved plan.', pending: false },
      { round: 2, entry: null, note: null, pending: true },
    ])
    expect(roundRowsOf(rounds, OTHER, 'M1.2')).toEqual([])
  })

  it('stops waiting once the item is scored in the current round', () => {
    const rounds = { requests: [request()], units: { 'point-31': [BEFORE, AFTER] } }
    expect(isAwaitingRescore(rounds, POINT, 'M1.1')).toBe(false)
    expect(roundRowsOf(rounds, POINT, 'M1.1').map((row) => row.pending)).toEqual([false, false])
  })

  it('opens no round while the request is still open, and marks files added after it', () => {
    const open = { requests: [request({ status: 'open', resolvedAt: null })], units: {} }
    expect(currentRoundRequest(open)).toBeNull()
    expect(isAwaitingRescore(open, POINT, 'M1.1')).toBe(false)
    expect(currentRoundRequest(null)).toBeNull()

    const rounds = { requests: [request()], units: {} }
    expect(isNewInRound(rounds, { uploadedAt: '2026-11-01T08:00:00Z' })).toBe(true)
    expect(isNewInRound(rounds, { uploadedAt: '2026-10-20T08:00:00Z' })).toBe(false)
    expect(isNewInRound(open, { uploadedAt: '2026-11-01T08:00:00Z' })).toBe(false)
  })
})
