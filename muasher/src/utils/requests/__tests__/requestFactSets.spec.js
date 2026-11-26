import { describe, expect, it } from 'vitest'
import {
  REQUEST_FACT_LABELS,
  REQUEST_FACTS as F,
  requestFactGroupsOf,
  requestFactSetOf,
  requestFactsOf,
  requestStatusSince,
} from '../requestFactSets'

// The staff request record's facts per path and state (request page v4.1).
const BASE = {
  path: 'accredited',
  status: 'under-review',
  entity: { id: 1 },
  assessor: { id: 10 },
  submittedAt: '2026-10-25T08:00:00Z',
  dueAt: '2026-11-08T08:00:00Z',
  assignedAt: '2026-10-26T11:00:00Z',
  completedAt: null,
  completionDeadline: null,
  awardCycle: null,
  region: null,
  record: { createdAt: '2026-10-09T07:50:00Z', updatedAt: null, statusHistory: [] },
  assignmentHistory: [{ id: 1 }],
  completionRequests: [],
  payment: { paid: true, details: null },
}

describe('requestFactsOf', () => {
  it('splits an accredited request under review into assignment, dates and details', () => {
    expect(requestFactsOf(BASE)).toEqual({
      assignment: [F.ASSESSOR, F.ASSIGNED],
      timeline: [F.SUBMITTED, F.DUE],
      details: [F.LEVEL, F.FEE, F.ASSIGNMENTS],
    })
  })

  it('gives a draft only its dates and leaves out the ones it has not reached', () => {
    const draft = {
      ...BASE,
      status: 'draft',
      assessor: null,
      submittedAt: null,
      dueAt: null,
      assignedAt: null,
    }
    expect(requestFactsOf(draft)).toEqual({
      assignment: [],
      timeline: [F.OPENED],
      details: [F.LEVEL, F.FEE],
    })
  })

  it('adds the completion deadline and the completion requests while a completion is open', () => {
    const facts = requestFactsOf({
      ...BASE,
      status: 'needs-completion',
      completionDeadline: '2026-12-01T00:00:00Z',
      completionRequests: [{ id: 1, status: 'open' }],
    })
    expect(facts.timeline).toEqual([F.SUBMITTED, F.DUE, F.DEADLINE])
    expect(facts.details).toContain(F.COMPLETIONS)
  })

  it('drops the fee from the details when the payment tab shows it', () => {
    const facts = requestFactsOf({
      ...BASE,
      payment: { paid: true, details: { reference: 'PAY-1' } },
    })
    expect(facts.details).not.toContain(F.FEE)
  })

  it('shows an advanced request its cycle and region and no award stage', () => {
    const facts = requestFactsOf({
      ...BASE,
      path: 'advanced',
      status: 'under-initial-assessment',
      awardCycle: { id: 31 },
      region: { id: 1 },
    })
    expect(facts.details).toEqual([F.CYCLE, F.REGION, F.LEVEL, F.ASSIGNMENTS])
    expect(Object.values(F)).not.toContain('awardStage')
  })

  it('falls back to a safe set for a state it does not know', () => {
    expect(requestFactSetOf({ path: 'accredited', status: 'unknown' }).timeline).toEqual([
      F.SUBMITTED,
      F.DUE,
    ])
  })

  it('labels every fact', () => {
    for (const fact of Object.values(F)) expect(REQUEST_FACT_LABELS[fact]).toBeTruthy()
  })
})

describe('requestStatusSince', () => {
  it('takes the last status change, then the submission, then the opening date', () => {
    expect(
      requestStatusSince({ ...BASE, record: { statusHistory: [{ at: 'a' }, { at: 'b' }] } }),
    ).toBe('b')
    expect(requestStatusSince(BASE)).toBe(BASE.submittedAt)
    expect(requestStatusSince({ ...BASE, submittedAt: null })).toBe(BASE.record.createdAt)
  })
})

describe('requestFactGroupsOf', () => {
  it('gives a page without the summary card the request, its assignment, dates and details', () => {
    expect(requestFactGroupsOf(BASE)).toEqual([
      { key: 'request', facts: [F.ENTITY, F.STATUS] },
      { key: 'assignment', facts: [F.ASSESSOR, F.ASSIGNED] },
      { key: 'timeline', facts: [F.SUBMITTED, F.DUE] },
      { key: 'details', facts: [F.FEE, F.ASSIGNMENTS] },
    ])
  })
})
