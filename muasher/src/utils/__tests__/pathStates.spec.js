import { describe, expect, it } from 'vitest'
import { accreditedStateOf, awardStateOf, isFreePath, selfStateOf } from '@/utils/pathStates'
import { accreditedAction, awardAction, rowAction } from '@/utils/pathActions'

const AVAILABLE = { price: null, isFree: true, availability: 'available' }

function accredited(extra = {}) {
  return {
    pricing: AVAILABLE,
    awaitingPayment: null,
    openRequest: null,
    periodStatus: 'not-submitted',
    lastResult: null,
    ...extra,
  }
}

describe('path states (03 §19.2 header states)', () => {
  it('derives the accredited header state, payment first', () => {
    expect(accreditedStateOf(accredited())).toBe('apply')
    expect(
      accreditedStateOf(
        accredited({ awaitingPayment: { paymentId: 'p', channel: 'bank_transfer' } }),
      ),
    ).toBe('awaitingPayment')
    expect(accreditedStateOf(accredited({ openRequest: { id: 1, status: 'draft' } }))).toBe('draft')
    expect(
      accreditedStateOf(accredited({ openRequest: { id: 1, status: 'needs-completion' } })),
    ).toBe('needsCompletion')
    expect(accreditedStateOf(accredited({ openRequest: { id: 1, status: 'assigned' } }))).toBe(
      'inReview',
    )
    expect(accreditedStateOf(accredited({ periodStatus: 'assessed' }))).toBe('assessed')
    expect(
      accreditedStateOf(accredited({ pricing: { ...AVAILABLE, availability: 'paused' } })),
    ).toBe('unavailable')
    expect(
      accreditedStateOf(accredited({ pricing: { ...AVAILABLE, availability: 'hidden' } })),
    ).toBe('hidden')
  })

  it('opens the transfer detail for a transfer under review and the form for a draft', () => {
    const transfer = accredited({ awaitingPayment: { paymentId: 'p-1', channel: 'bank_transfer' } })
    expect(accreditedAction('awaitingPayment', transfer).to).toEqual({
      name: 'payments.mine.detail',
      params: { id: 'p-1' },
    })
    const draft = accredited({ openRequest: { id: 42, status: 'draft' } })
    expect(accreditedAction('draft', draft).to).toEqual({
      name: 'assessment.accredited.form',
      params: { id: '42' },
    })
  })

  it('derives the award states including the closed window and the masked request', () => {
    const base = {
      pricing: AVAILABLE,
      windowState: 'open',
      eligibility: { eligible: true, reason: null },
      openRequest: null,
      announced: false,
      lastResult: null,
    }
    expect(awardStateOf(base)).toBe('eligible')
    expect(awardStateOf({ ...base, eligibility: { eligible: false, reason: 'region' } })).toBe(
      'notEligible',
    )
    expect(awardStateOf({ ...base, windowState: 'closed' })).toBe('closed')
    expect(awardStateOf({ ...base, windowState: 'upcoming' })).toBe('upcoming')
    expect(awardStateOf({ ...base, windowState: 'none' })).toBe('none')
    expect(awardStateOf({ ...base, openRequest: { id: 5, status: 'submitted' } })).toBe('submitted')
    expect(awardAction('submitted', base)).toBeNull()
    expect(selfStateOf({ pricing: AVAILABLE, openDraft: { id: 3 } })).toBe('draft')
  })

  it('gives a missed-period row no action', () => {
    expect(rowAction('accredited', { id: null, rowType: 'missed_period' })).toBeNull()
    expect(rowAction('accredited', { id: 9, isFrozen: false })).toEqual({
      name: 'paths.accredited.request',
      params: { id: '9' },
    })
  })
})

describe('isFreePath', () => {
  const PRICE = { priceBeforeVat: '100.00', total: '115.00', currency: 'SAR' }

  it('is free when the path is available and carries no price to pay', () => {
    expect(isFreePath({ pricing: { price: null, isFree: true, availability: 'available' } })).toBe(
      true,
    )
    expect(isFreePath({ pricing: { price: PRICE, isFree: true, availability: 'available' } })).toBe(
      true,
    )
  })

  it('is not free when a price applies, the path is unavailable, or no pricing is known', () => {
    expect(
      isFreePath({ pricing: { price: PRICE, isFree: false, availability: 'available' } }),
    ).toBe(false)
    expect(
      isFreePath({ pricing: { price: null, isFree: true, availability: 'unavailable' } }),
    ).toBe(false)
    expect(isFreePath(null)).toBe(false)
  })
})
