import { describe, expect, it } from 'vitest'
import { KIT_FACT_LABELS, kitFactsOf, kitStatusSince } from '../kitFactSets'

// The Kit request record's facts per state (request page v4.1).
const KIT = {
  status: 'assigned',
  paidAt: '2026-07-06T12:00:00+03:00',
  assignedAt: '2026-07-07T10:00:00+03:00',
  startedAt: null,
  completedAt: null,
}

describe('kitFactsOf', () => {
  it('shows a Kit awaiting an evaluator its evaluator and its payment date', () => {
    expect(kitFactsOf({ ...KIT, status: 'awaiting-assignment', assignedAt: null })).toEqual({
      assignment: ['evaluator'],
      timeline: ['paidAt'],
    })
  })

  it('leaves out the start date an assigned Kit has not reached', () => {
    expect(kitFactsOf(KIT)).toEqual({
      assignment: ['evaluator', 'assignedAt'],
      timeline: ['paidAt'],
    })
  })

  it('shows a completed Kit its completion date', () => {
    const facts = kitFactsOf({
      ...KIT,
      status: 'completed',
      completedAt: '2026-08-01T09:00:00+03:00',
    })
    expect(facts.timeline).toEqual(['paidAt', 'completedAt'])
  })

  it('labels every fact', () => {
    for (const key of ['evaluator', 'paidAt', 'assignedAt', 'startedAt', 'completedAt'])
      expect(KIT_FACT_LABELS[key]).toBeTruthy()
  })
})

describe('kitStatusSince', () => {
  it('dates the status by the step that started it, falling back to the payment', () => {
    expect(kitStatusSince(KIT)).toBe(KIT.assignedAt)
    expect(kitStatusSince({ ...KIT, status: 'awaiting-assignment' })).toBe(KIT.paidAt)
    expect(kitStatusSince({ ...KIT, status: 'in-analysis' })).toBe(KIT.paidAt)
  })
})
