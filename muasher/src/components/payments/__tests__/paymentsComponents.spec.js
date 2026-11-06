import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import PricingBreakdown from '@/components/payments/PricingBreakdown.vue'
import PaymentStatusBadge from '@/components/payments/PaymentStatusBadge.vue'
import ReceiptCard from '@/components/payments/ReceiptCard.vue'
import PaymentOutcome from '@/components/payments/PaymentOutcome.vue'
import CheckoutStateNotice from '@/components/payments/CheckoutStateNotice.vue'
import TestModeNotice from '@/components/payments/TestModeNotice.vue'
import { CHECKOUT_PHASES } from '@/composables/useCheckout'
import { RETURN_STATES } from '@/composables/usePaymentReturn'
import { createTestGlobals } from '@/test-utils'

const EASTERN_DIGITS = /[\u0660-\u0669\u06F0-\u06F9]/
const PRICING = {
  priceBeforeVat: '1000.00',
  vatRate: '15.00',
  vatAmount: '150.00',
  total: '1150.00',
  currency: 'SAR',
}
const RECEIPT = {
  id: 'r-1',
  number: 'RCP-2026-000001',
  status: 'issued',
  isTest: false,
  issuedAt: '2026-08-12T08:14:00Z',
  voidedAt: null,
  reissueCount: 0,
  lastReissuedAt: null,
  snapshot: {
    paymentReference: 'PAY-2026-000001',
    entityReference: 'ENT-005001',
    entityName: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity — Riyadh' },
    purpose: 'accredited',
    ...PRICING,
    paidAt: '2026-08-12T08:14:00Z',
    method: 'mada',
    cardLast4: '4821',
    providerPaymentId: 'mock_pay_paid_0001',
  },
}

function mountWith(component, props, { locale = 'en', slots } = {}) {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  return mount(component, { props, slots, global: globals })
}

describe('money and pricing', () => {
  it('shows the amount with two decimals and the translated currency label, never glued', () => {
    const ar = mountWith(MoneyAmount, { value: '1150.00' }, { locale: 'ar' })
    expect(ar.find('.money-amount__value').text()).toBe('1,150.00')
    expect(ar.find('.money-amount__currency').text()).toBe('ر.س')
    expect(ar.text()).not.toMatch(EASTERN_DIGITS)
    expect(mountWith(MoneyAmount, { value: null }).text()).toBe('—')
  })

  it('orders the breakdown before VAT, then VAT with its rate, then the total', () => {
    const wrapper = mountWith(PricingBreakdown, { pricing: PRICING })
    const rows = wrapper.findAll('.pricing-breakdown__row').map((row) => row.text())
    expect(rows).toEqual(['Price before VAT1,000.00SAR', 'VAT (15%)150.00SAR', 'Total1,150.00SAR'])
  })

  it('labels every payment status with text, not color alone', () => {
    const labels = ['created', 'pending', 'paid', 'failed', 'cancelled', 'refunded'].map((status) =>
      mountWith(PaymentStatusBadge, { status }).text(),
    )
    expect(labels).toEqual([
      'Awaiting payment',
      'Processing',
      'Paid',
      'Failed',
      'Cancelled',
      'Refunded',
    ])
  })
})

describe('ReceiptCard', () => {
  it('shows the official number with Gregorian and Umm al-Qura dates and emits the download', async () => {
    const wrapper = mountWith(ReceiptCard, { receipt: RECEIPT }, { locale: 'ar' })
    expect(wrapper.find('.receipt-card__number').text()).toBe('RCP-2026-000001')
    expect(wrapper.find('.receipt-card__hijri').text()).toContain('1448')
    expect(wrapper.text()).not.toMatch(EASTERN_DIGITS)
    await wrapper.find('.receipt-card__actions .btn').trigger('click')
    expect(wrapper.emitted('download')).toHaveLength(1)
  })

  it('marks a TEST receipt without a number and a voided receipt with VOID', () => {
    const test = mountWith(ReceiptCard, { receipt: { ...RECEIPT, number: null, isTest: true } })
    expect(test.find('.receipt-card__number').text()).toBe('TEST receipt')
    expect(test.find('.receipt-card__watermark').text()).toContain('TEST')
    const voided = mountWith(ReceiptCard, {
      receipt: { ...RECEIPT, status: 'voided', voidedAt: '2026-09-02T09:20:00Z' },
    })
    expect(voided.find('.receipt-card__watermark--void').text()).toContain('VOID')
    const unavailable = mountWith(ReceiptCard, { receipt: RECEIPT, unavailable: true })
    expect(unavailable.find('.payment-notice--warning').text()).toContain('temporarily unavailable')
  })

  it('shows the VAT registration number row only when the receipt carries one', () => {
    const withNumber = mountWith(
      ReceiptCard,
      { receipt: { ...RECEIPT, vatRegistrationNumber: '300012345600003' } },
      { locale: 'ar' },
    )
    const row = withNumber.find('[data-testid="receipt-vat-number"]')
    expect(row.find('dt').text()).toBe('الرقم الضريبي')
    expect(row.find('bdi').text()).toBe('300012345600003')
    const withoutNumber = mountWith(ReceiptCard, {
      receipt: { ...RECEIPT, vatRegistrationNumber: null },
    })
    expect(withoutNumber.find('[data-testid="receipt-vat-number"]').exists()).toBe(false)
    expect(withoutNumber.text()).not.toContain('VAT registration number')
  })
})

describe('payment outcome and checkout notices', () => {
  it('shows the failure reason from the server on a failed payment', () => {
    const wrapper = mountWith(PaymentOutcome, {
      state: RETURN_STATES.FAILED,
      payment: { failureReason: 'insufficient_funds' },
    })
    expect(wrapper.find('.payment-notice__message').text()).toBe('The card has insufficient funds.')
    const notify = mountWith(PaymentOutcome, { state: RETURN_STATES.NOTIFY, payment: null })
    expect(notify.find('.payment-notice__title').text()).toBe('We will notify you')
    expect(notify.attributes('role')).toBe('status')
  })

  it('offers a retry only for retryable checkout failures and a new order after expiry', async () => {
    const unavailable = mountWith(CheckoutStateNotice, {
      phase: CHECKOUT_PHASES.BLOCKED,
      failure: {
        code: 'provider_unavailable',
        error: { normalized: { code: 'provider_unavailable' } },
      },
    })
    expect(unavailable.find('.payment-notice__message').text()).toContain('temporarily unavailable')
    await unavailable.find('.btn').trigger('click')
    expect(unavailable.emitted('restart')).toHaveLength(1)
    const price = mountWith(CheckoutStateNotice, {
      phase: CHECKOUT_PHASES.BLOCKED,
      failure: {
        code: 'payment.price_not_configured',
        error: { normalized: { code: 'payment.price_not_configured' } },
      },
    })
    expect(price.find('.btn').exists()).toBe(false)
    const expired = mountWith(CheckoutStateNotice, {
      phase: CHECKOUT_PHASES.EXPIRED,
      payment: { id: 'p-1' },
    })
    expect(expired.find('.payment-notice__title').text()).toBe(
      'The payment order expired and was cancelled',
    )
  })

  it('states that the test mode charges nothing', () => {
    expect(mountWith(TestModeNotice, {}, { locale: 'ar' }).text()).toContain(
      'لا يُخصم أي مبلغ فعلي',
    )
  })
})
