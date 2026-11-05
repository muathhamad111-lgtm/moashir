import { describe, expect, it } from 'vitest'
import { NEXT_STEPS, STATION_STATES as S, useEntityJourney } from '@/composables/useEntityJourney'

const ENTITY = { status: 'active', verifiedAt: '2026-10-12T08:00:00Z' }
const SELF_RESULT = {
  id: 9001,
  number: 'SLF-2026-000001',
  status: 'result-available',
  result: { percent: '78.00' },
  completedAt: '2026-10-28T08:00:00Z',
}
const PROGRESS = {
  subsTotal: 16,
  subsComplete: 12,
  remaining: [{ subCriterionId: 41, code: '1.3', name: null }],
}

function active(status, extra = {}) {
  return { id: 14, number: 'ACC-2026-000014', status, progress: null, ...extra }
}

function journey(overrides = {}) {
  return useEntityJourney({
    entity: ENTITY,
    selfAssessments: [],
    accredited: { awaitingPayment: null, active: null, latestClosed: null },
    certificates: [],
    actionItems: [],
    ...overrides,
  })
}

function states(result) {
  return result.stations.map((station) => station.state)
}

describe('useEntityJourney', () => {
  it('starts with the self assessment when nothing exists yet', () => {
    const result = journey()
    expect(states(result)).toEqual([S.DONE, S.CURRENT, S.UPCOMING, S.UPCOMING])
    expect(result.currentNumber).toBe(2)
    expect(result.nextStep).toEqual({ kind: NEXT_STEPS.SELF_START })
  })

  it('marks the profile current until the entity is verified', () => {
    const result = journey({ entity: { status: 'pending_verification', verifiedAt: null } })
    expect(result.stations[0]).toMatchObject({ state: S.CURRENT, meta: { key: 'pending' } })
  })

  it('shows the D1 state: self done, accredited draft with progress', () => {
    const result = journey({
      selfAssessments: [SELF_RESULT],
      accredited: { awaitingPayment: null, active: active('draft', { progress: PROGRESS }) },
    })
    expect(states(result)).toEqual([S.DONE, S.DONE, S.CURRENT, S.UPCOMING])
    expect(result.stations[1].meta).toEqual({
      key: 'result',
      params: { percent: '78.00', date: '2026-10-28T08:00:00Z' },
    })
    expect(result.stations[2].meta).toEqual({ key: 'draft', params: { done: 12, total: 16 } })
    expect(result.currentNumber).toBe(3)
    expect(result.nextStep).toMatchObject({
      kind: NEXT_STEPS.EVIDENCE,
      assessmentId: 14,
      reference: 'ACC-2026-000014',
      progress: PROGRESS,
    })
  })

  it('puts an open payment order first', () => {
    const result = journey({
      selfAssessments: [SELF_RESULT],
      accredited: {
        awaitingPayment: { paymentId: 'p-1', reference: 'PAY-2026-000001', expiresAt: 'x' },
        active: null,
      },
    })
    expect(result.stations[2].meta.key).toBe('awaitingPayment')
    expect(result.nextStep).toEqual({
      kind: NEXT_STEPS.PAYMENT,
      paymentId: 'p-1',
      reference: 'PAY-2026-000001',
      expiresAt: 'x',
    })
  })

  it('takes the completion deadline from the Action Center item', () => {
    const result = journey({
      accredited: { active: active('needs-completion', { progress: PROGRESS }) },
      actionItems: [{ key: 'complete_accredited_request', dueAt: '2026-11-10T20:59:00Z' }],
    })
    expect(result.stations[2].meta.key).toBe('needsCompletion')
    expect(result.nextStep).toMatchObject({
      kind: NEXT_STEPS.COMPLETION,
      dueAt: '2026-11-10T20:59:00Z',
    })
  })

  it.each(['submitted', 'assigned', 'under-review'])(
    'informs while the request is %s',
    (status) => {
      const result = journey({ accredited: { active: active(status) } })
      expect(result.stations[2].meta.key).toBe('inReview')
      expect(result.nextStep).toMatchObject({ kind: NEXT_STEPS.IN_REVIEW, status })
    },
  )

  it('keeps the self station upcoming while an accredited request is open without a self assessment', () => {
    const result = journey({ accredited: { active: active('draft') } })
    expect(result.stations[1].state).toBe(S.UPCOMING)
    expect(result.stations[2].meta.key).toBe('draftOpen')
  })

  it('continues a self draft before suggesting the accredited path', () => {
    const result = journey({
      selfAssessments: [SELF_RESULT, { id: 9002, status: 'draft' }],
    })
    expect(result.stations[1].state).toBe(S.DONE)
    expect(result.nextStep).toEqual({ kind: NEXT_STEPS.SELF_DRAFT, assessmentId: 9002 })
  })

  it('waits for a submitted self assessment', () => {
    const result = journey({ selfAssessments: [{ id: 9003, status: 'submitted' }] })
    expect(result.stations[1].meta.key).toBe('submitted')
    expect(result.nextStep.kind).toBe(NEXT_STEPS.SELF_SUBMITTED)
  })

  it('suggests the accredited assessment after the self result', () => {
    const result = journey({ selfAssessments: [SELF_RESULT] })
    expect(result.nextStep).toEqual({ kind: NEXT_STEPS.ACCREDITED_START, restart: false })
    expect(result.currentNumber).toBe(3)
  })

  it.each(['refunded', 'declined'])('offers a new request after a %s request', (status) => {
    const result = journey({
      selfAssessments: [SELF_RESULT],
      accredited: { latestClosed: { id: 9, number: 'ACC-2026-000009', status } },
    })
    expect(result.stations[2].meta.key).toBe('restart')
    expect(result.nextStep).toEqual({ kind: NEXT_STEPS.ACCREDITED_START, restart: true })
  })

  it('marks the certificate as being issued after completion', () => {
    const result = journey({
      selfAssessments: [SELF_RESULT],
      accredited: {
        latestClosed: {
          id: 9,
          number: 'ACC-2026-000009',
          status: 'completed',
          completedAt: '2026-10-30T08:00:00Z',
        },
      },
    })
    expect(states(result)).toEqual([S.DONE, S.DONE, S.DONE, S.CURRENT])
    expect(result.nextStep).toMatchObject({ kind: NEXT_STEPS.CERTIFICATE, assessmentId: 9 })
  })

  it('completes the journey with an issued certificate', () => {
    const result = journey({
      selfAssessments: [SELF_RESULT],
      accredited: { latestClosed: { id: 9, status: 'completed', completedAt: 'x' } },
      certificates: [{ number: 'CRT-2026-000001', status: 'issued' }],
    })
    expect(result.isComplete).toBe(true)
    expect(result.currentNumber).toBe(4)
    expect(result.stations[3].meta).toEqual({
      key: 'issued',
      params: { number: 'CRT-2026-000001' },
    })
  })

  it('shows a suspended certificate as under review and ignores revoked ones', () => {
    const suspended = journey({ certificates: [{ number: 'CRT-1', status: 'suspended' }] })
    expect(suspended.stations[3].meta.key).toBe('underReview')
    const revoked = journey({ certificates: [{ number: 'CRT-2', status: 'revoked' }] })
    expect(revoked.stations[3].state).toBe(S.UPCOMING)
  })

  it('uses the latest self result', () => {
    const older = { ...SELF_RESULT, id: 1, result: { percent: '60.00' }, completedAt: '2026-01-01' }
    const result = journey({ selfAssessments: [older, SELF_RESULT] })
    expect(result.selfResult.id).toBe(9001)
  })
})
