import { describe, expect, it } from 'vitest'
import { accreditedStateOf, awardStateOf, isHidden, selfStateOf } from '@/utils/pathStates'
import { PATH_RUNS, accreditedAction, awardAction, selfAction } from '@/utils/pathActions'
import { nextStepView } from '@/utils/nextStepView'
import { entryRouteOf, nextRouteOf } from '@/utils/payments/myPaymentRow'
import { pathPricePayload, pathPriceValues, priceString } from '@/utils/settings/pathPriceForm'

const QUOTE = {
  priceBeforeVat: '100.00',
  vatRate: '15.00',
  vatAmount: '15.00',
  total: '115.00',
  currency: 'SAR',
}
const FREE = { price: null, isFree: true, availability: 'available' }
const PRICED = { price: QUOTE, isFree: false, availability: 'available' }

function self(extra = {}) {
  return { pricing: FREE, isListed: true, awaitingPayment: null, openDraft: null, ...extra }
}

function award(extra = {}) {
  return {
    pricing: FREE,
    isListed: true,
    windowState: 'open',
    eligibility: { eligible: true, reason: null },
    awaitingPayment: null,
    openRequest: null,
    windowClosedBeforePayment: false,
    announced: false,
    lastResult: null,
    ...extra,
  }
}

describe('priced and listed path states (Task-80 §3, §4)', () => {
  it('offers «ادفع وابدأ» for a priced self path and the payment while its order is open', () => {
    expect(selfStateOf(self())).toBe('start')
    expect(selfStateOf(self({ pricing: PRICED }))).toBe('payStart')
    expect(selfAction('payStart', self({ pricing: PRICED })).to).toEqual({
      name: 'assessment.self.pay',
    })
    const open = self({
      pricing: PRICED,
      awaitingPayment: { paymentId: 'p-9', channel: 'electronic', reference: 'PAY-1' },
    })
    expect(selfStateOf(open)).toBe('awaitingPayment')
    expect(selfAction('awaitingPayment', open).to).toEqual({
      name: 'payments.mine.complete',
      params: { id: 'p-9' },
    })
  })

  it('hides a path only when it is not listed and keeps a listed hidden path without a start', () => {
    expect(isHidden(self({ isListed: false }))).toBe(true)
    expect(selfStateOf(self({ isListed: false }))).toBe('hidden')
    const listedHidden = self({ pricing: { ...FREE, availability: 'hidden' } })
    expect(selfStateOf(listedHidden)).toBe('unavailable')
    expect(selfAction('unavailable', listedHidden)).toBeNull()
    expect(isHidden({ pricing: { ...FREE, availability: 'hidden' } })).toBe(true)
  })

  it('starts a free accredited request in place and sends a priced one to the checkout', () => {
    const base = { awaitingPayment: null, openRequest: null, periodStatus: 'not-submitted' }
    const free = { ...base, pricing: FREE, isListed: true }
    expect(accreditedStateOf(free)).toBe('apply')
    expect(accreditedAction('apply', free)).toMatchObject({
      run: PATH_RUNS.START_ACCREDITED,
      labelKey: 'paths.actions.startAccredited',
    })
    expect(accreditedAction('apply', { ...free, pricing: PRICED }).to).toEqual({
      name: 'assessment.accredited.pay',
    })
  })

  it('shows the award payment, the late payment and the paused path before the window states', () => {
    expect(
      awardStateOf(award({ awaitingPayment: { paymentId: 'p-2', channel: 'electronic' } })),
    ).toBe('awaitingPayment')
    expect(
      awardAction('awaitingPayment', award({ awaitingPayment: { paymentId: 'p-2' } })).to.name,
    ).toBe('payments.mine.complete')
    const late = award({
      windowState: 'closed',
      openRequest: { id: 7, status: 'draft' },
      windowClosedBeforePayment: true,
    })
    expect(awardStateOf(late)).toBe('latePayment')
    expect(awardAction('latePayment', late)).toBeNull()
    expect(awardStateOf(award({ pricing: { ...FREE, availability: 'paused' } }))).toBe(
      'unavailable',
    )
  })
})

describe('rank-1 next step (Task-80 §9)', () => {
  const step = {
    rank: 1,
    key: 'settle_payment',
    path: 'advanced',
    target: { route: 'paths.award', params: {}, query: {}, href: '/paths/award' },
    context: {
      paymentReference: 'PAY-2026-000121',
      paymentChannel: 'electronic',
      paymentStatus: 'pending',
      paymentExpiresAt: '2026-11-10T09:30:00+00:00',
    },
  }

  it('maps the advanced path code to the award name and exposes the channel and expiry', () => {
    const view = nextStepView(step)
    expect(view.pathKey).toBe('award')
    expect(view.titleKey).toBe('portal.home.next.steps.settle_payment.title')
    expect(view.payment).toEqual({
      channel: 'electronic',
      reference: 'PAY-2026-000121',
      expiresAt: '2026-11-10T09:30:00+00:00',
    })
  })

  it('asks for a new order after a rejected transfer', () => {
    const view = nextStepView({
      ...step,
      context: { ...step.context, paymentChannel: 'bank_transfer', paymentStatus: 'rejected' },
    })
    expect(view.titleKey).toBe('portal.home.next.steps.settle_payment_rejected.title')
    expect(view.payment.channel).toBe('bank_transfer')
  })
})

describe('payment routes for the new purposes', () => {
  it('reopens the award checkout on its cycle and lands on the opened draft', () => {
    expect(entryRouteOf({ purpose: 'award', awardCycle: { id: 31 } })).toEqual({
      name: 'assessment.advanced.pay',
      query: { cycle: '31' },
    })
    expect(entryRouteOf({ purpose: 'self' })).toEqual({ name: 'assessment.self.pay' })
    expect(nextRouteOf({ purpose: 'award', request: { id: 77, path: 'advanced' } })).toEqual({
      name: 'assessment.advanced.form',
      params: { id: '77' },
    })
    expect(nextRouteOf({ purpose: 'self', request: null })).toEqual({ name: 'paths.self' })
  })
})

describe('path price form', () => {
  it('normalises the price and sends the base version for the optimistic lock', () => {
    const row = { key: 'self', inForce: { id: 12, priceBeforeVat: '0.00', availability: 'hidden' } }
    expect(pathPriceValues(row)).toEqual({
      priceBeforeVat: '0.00',
      availability: 'hidden',
      effectiveFrom: '',
    })
    expect(priceString('١٢٠٠٫٥'.replace('٫', '.'))).toBe('1200.50')
    expect(
      pathPricePayload({ priceBeforeVat: '75', availability: 'available', effectiveFrom: '' }, row),
    ).toEqual({
      priceBeforeVat: '75.00',
      availability: 'available',
      effectiveFrom: null,
      baseVersionId: 12,
    })
    expect(pathPriceValues({ key: 'bag', inForce: null }).availability).toBe('available')
  })
})
