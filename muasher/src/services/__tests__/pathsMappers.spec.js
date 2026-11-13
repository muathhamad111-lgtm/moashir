import { describe, expect, it } from 'vitest'
import { fromWire } from '@/api/caseMapping'
import { toEntityBoard } from '@/services/mappers/dashboard'
import { toAccreditedCurrent } from '@/services/mappers/accredited'
import { toPathRequest } from '@/services/pathsService'

const PERIOD = {
  id: 7,
  key: '2027-Q1',
  cadence: 'quarterly',
  label: { ar: 'الربع الأول 2027', en: 'Q1 2027' },
  starts_on: '2027-01-01',
  ends_on: '2027-03-31',
  starts_at: '2026-12-31T21:00:00+00:00',
  ends_at: '2027-03-31T21:00:00+00:00',
  is_shortened: false,
}

const BOARD = {
  cards: [],
  widgets: [
    {
      key: 'level',
      data: {
        level: 'silver',
        certificate: {
          number: 'CERT-2027-00012',
          status: 'issued',
          valid_until: '2027-06-30',
          is_expired: false,
        },
      },
    },
    {
      key: 'paths',
      data: {
        self: {
          pricing: { price: null, is_free: true, availability: 'available' },
          open_draft: null,
          last_result: null,
        },
        accredited: {
          pricing: { price: null, is_free: false, availability: 'paused' },
          is_live: true,
          cadence: 'quarterly',
          current_period: PERIOD,
          period_status: 'not_submitted',
          next_period: { ...PERIOD, key: '2027-Q2', notice_on: '2027-03-18' },
          next_submission_from: null,
          awaiting_payment: null,
          open_request: {
            id: 42,
            number: 'ACC-2027-00042',
            status: 'needs_completion',
            needs_completion: true,
            completion_deadline: '2027-02-28T20:59:59+00:00',
            remaining_count: 3,
            submitted_at: null,
            period_state: 'assigned',
            period_label: PERIOD.label,
          },
          last_result: null,
          kit: { status: null, request_id: null },
        },
        award: {
          pricing: { price: null, is_free: true, availability: 'available' },
          window_state: 'upcoming',
          cycle: null,
          next_cycle: null,
          eligibility: { eligible: false, reason: 'window_not_open' },
          open_request: null,
          announced: false,
          last_result: null,
        },
      },
    },
    {
      key: 'next_step',
      data: {
        rank: 2,
        key: 'complete_request',
        path: 'accredited',
        target: {
          route: 'paths.accredited.request',
          params: { id: 42 },
          query: { tab: 'request' },
          href: '/paths/accredited/42?tab=request',
        },
        context: {
          assessment_id: 42,
          number: 'ACC-2027-00042',
          status: 'needs_completion',
          payment_id: null,
          is_paid: true,
          deadline_at: '2027-02-28T20:59:59+00:00',
          period_ends_on: '2027-03-31',
          cycle_id: null,
        },
      },
    },
  ],
}

describe('Task-79 contract mappers', () => {
  it('maps the level, paths and next_step widgets of the entity board', () => {
    const board = toEntityBoard(fromWire(BOARD))
    expect(board.level.certificate.validUntil).toBe('2027-06-30')
    expect(board.paths.accredited.periodStatus).toBe('not-submitted')
    expect(board.paths.accredited.openRequest.status).toBe('needs-completion')
    expect(board.paths.accredited.nextPeriod.noticeOn).toBe('2027-03-18')
    expect(board.paths.award.windowState).toBe('upcoming')
    expect(board.nextStep).toMatchObject({ rank: 2, key: 'complete_request' })
    expect(board.nextStep.context.periodEndsOn).toBe('2027-03-31')
  })

  it('maps the periodic, submission, apply and pricing keys of accredited/current', () => {
    const current = toAccreditedCurrent(
      fromWire({
        awaiting_payment: null,
        active: null,
        latest_closed: null,
        periodic: {
          is_live: true,
          current_period: PERIOD,
          period_status: 'assessed',
          next_submission_from: '2027-04-01',
        },
        submission: {
          can_submit_now: false,
          reason: 'period_already_assessed',
          next_submission_from: '2027-04-01',
        },
        apply: { can_start: true, reason: null, warning: 'period_already_assessed' },
        pricing: { price: null, is_free: true, availability: 'available' },
      }),
    )
    expect(current.periodic.currentPeriod.label.en).toBe('Q1 2027')
    expect(current.submission).toEqual({
      canSubmitNow: false,
      reason: 'period_already_assessed',
      nextSubmissionFrom: '2027-04-01',
    })
    expect(current.apply.warning).toBe('period_already_assessed')
    expect(current.pricing.isFree).toBe(true)
  })

  it('maps a missed-period row without inventing a number', () => {
    const row = toPathRequest(
      fromWire({
        row_type: 'missed_period',
        row_key: 'period-6',
        id: null,
        number: null,
        path: 'accredited',
        status: null,
        period_status: 'missed',
        period_state: 'assigned',
        period: PERIOD,
        period_label: PERIOD.label,
      }),
    )
    expect(row).toMatchObject({
      rowType: 'missed_period',
      rowKey: 'period-6',
      number: '',
      periodStatus: 'missed',
    })
  })
})
