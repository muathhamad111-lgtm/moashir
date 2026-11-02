import { describe, expect, it } from 'vitest'
import { formatAmount, formatFileSize, formatRate } from '@/utils/payments/amountFormat'
import { moyasarConfigOf } from '@/utils/payments/moyasarConfig'
import { countdownLabel, providerPaymentIdOf, secondsUntil } from '@/utils/payments/paymentReturn'
import { errorCodeOf, financeErrorMessage, firstFieldErrors } from '@/utils/payments/financeErrors'

const CHECKOUT = {
  pattern: 'intent',
  mode: 'test',
  publishableKey: 'pk_test_fake_example_moashir01',
  amountHalalas: 115000,
  currency: 'SAR',
  description: 'Moashir PAY-2026-000021',
  callbackUrl: 'https://moashir.example.sa/payments/3f0c9a52-6d1e-4b7a-9c21-5a1e00000021/return',
  language: 'ar',
  methods: ['creditcard', 'applepay'],
  supportedNetworks: ['mada', 'visa', 'mastercard'],
  metadata: { moashir_payment_id: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000021', moashir_reference: 'PAY-2026-000021', purpose: 'accredited', app_env: 'local' },
  paymentIntent: { id: 'mock_pi_1', clientSecret: 'mock_pi_1_secret_mock' },
  applePay: { country: 'SA', label: 'منصة مؤشر', validateMerchantUrl: 'https://api.moyasar.com/v1/applepay/initiate' },
  expiresAt: '2026-11-01T10:30:00Z',
}

describe('amount formatting', () => {
  it('keeps the decimal string exact, two decimals, Western digits in both locales', () => {
    expect(formatAmount('1150.00', 'ar')).toBe('1,150.00')
    expect(formatAmount('9999999.99', 'en')).toBe('9,999,999.99')
    expect(formatAmount('0.1', 'en')).toBe('0.10')
    expect(formatAmount('not a number', 'en')).toBe('')
    expect(formatAmount(null, 'en')).toBe('')
  })

  it('formats the VAT rate and file sizes without Eastern Arabic digits', () => {
    expect(formatRate('15.00', 'ar')).toBe('15')
    expect(formatRate('5.50', 'en')).toBe('5.5')
    expect(formatFileSize(184320, 'ar')).toBe('180')
    expect(`${formatRate('15.00', 'ar')}${formatFileSize(184320, 'ar')}`).not.toMatch(/[\u0660-\u0669]/)
  })
})

describe('Moyasar Form configuration', () => {
  it('maps the checkout object to the intent options, keeping the metadata unchanged', () => {
    const onCompleted = () => null
    const config = moyasarConfigOf(CHECKOUT, { element: 'host', onCompleted })
    expect(config).toEqual({
      element: 'host',
      amount: 115000,
      currency: 'SAR',
      description: 'Moashir PAY-2026-000021',
      publishable_api_key: 'pk_test_fake_example_moashir01',
      callback_url: CHECKOUT.callbackUrl,
      language: 'ar',
      methods: ['creditcard', 'applepay'],
      supported_networks: ['mada', 'visa', 'mastercard'],
      metadata: CHECKOUT.metadata,
      use_payment_intent: true,
      payment_intent: { id: 'mock_pi_1', client_secret: 'mock_pi_1_secret_mock' },
      apple_pay: { country: 'SA', label: 'منصة مؤشر', validate_merchant_url: 'https://api.moyasar.com/v1/applepay/initiate' },
      on_completed: onCompleted,
    })
  })

  it('leaves the intent and Apple Pay options out when the checkout does not carry them', () => {
    const config = moyasarConfigOf({ ...CHECKOUT, pattern: 'form', applePay: null }, { element: 'host', onCompleted: null })
    expect(config).not.toHaveProperty('use_payment_intent')
    expect(config).not.toHaveProperty('payment_intent')
    expect(config).not.toHaveProperty('apple_pay')
  })
})

describe('return page helpers', () => {
  it('accepts only a well-formed gateway payment id from the query, ignoring status and message', () => {
    expect(providerPaymentIdOf({ id: 'mock_pay_8f2c', status: 'paid', message: 'APPROVED' })).toBe('mock_pay_8f2c')
    expect(providerPaymentIdOf({ id: ['mock_pay_8f2c', 'other'] })).toBe('mock_pay_8f2c')
    expect(providerPaymentIdOf({ id: 'bad id<script>' })).toBeNull()
    expect(providerPaymentIdOf({ status: 'paid' })).toBeNull()
    expect(providerPaymentIdOf({ id: 'a'.repeat(81) })).toBeNull()
  })

  it('counts down to the order expiry', () => {
    const now = Date.parse('2026-11-01T10:00:00Z')
    expect(secondsUntil('2026-11-01T10:29:30Z', now)).toBe(1770)
    expect(secondsUntil('2026-11-01T09:59:00Z', now)).toBe(0)
    expect(secondsUntil(null, now)).toBe(0)
    expect(countdownLabel(1770)).toBe('29:30')
    expect(countdownLabel(5)).toBe('00:05')
  })
})

describe('finance error messages', () => {
  const i18n = {
    te: (key) => ['finance.errors.refund.amount_must_equal_total', 'finance.errors.payment.price_not_configured'].includes(key),
    t: (key, params) => (params?.total ? `${key}:${params.total}` : key),
  }

  it('translates a known code, passes the payment total and falls back to the server message', () => {
    const amount = { normalized: { code: 'refund.amount_must_equal_total', meta: { paymentTotal: '1150.00' } } }
    expect(financeErrorMessage(i18n, amount, 'fallback')).toBe('finance.errors.refund.amount_must_equal_total:1150.00')
    expect(financeErrorMessage(i18n, { normalized: { code: 'payment.price_not_configured' } }, 'fallback')).toBe(
      'finance.errors.payment.price_not_configured',
    )
    expect(financeErrorMessage(i18n, { normalized: { code: 'other.code', message: 'Server text' } }, 'fallback')).toBe('Server text')
    expect(financeErrorMessage(i18n, new Error('offline'), 'fallback')).toBe('fallback')
    expect(errorCodeOf({ normalized: { code: 503 } })).toBeNull()
  })

  it('keeps the first message of every field', () => {
    expect(firstFieldErrors({ normalized: { fieldErrors: { amount: ['First', 'Second'], evidence: 'Only' } } })).toEqual({ amount: 'First', evidence: 'Only' })
    expect(firstFieldErrors(null)).toEqual({})
  })
})
