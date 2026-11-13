import { FIXTURE_ENTITY_LOGO_FILE } from './entityLogos'
export const FINANCE_YEAR = 2026

export const financeSettingsSeed = {
  accreditedPrice: '1000.00',
  bagPrice: '2500.00',
  vatRate: '15.00',
  orderValidityMinutes: 30,
}

export const ORDER_VALIDITY_LIMITS = { min: 5, max: 60 }

export const FINANCE_STAFF = {
  financeOfficer: { id: 22, name: 'سعود بن مبارك الدواسر' },
  systemAdmin: { id: 1, name: 'عبدالله بن سعد القحطاني' },
}

export const SEEDED_IDS = {
  paidAccredited: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000001',
  paidBag: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000002',
  refunded: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000003',
  failedLive: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000004',
  cancelledExpired: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000005',
  paidTest: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000006',
  latePaid: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000007',
  pendingLive: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000008',
  ownFailed: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000009',
  paidApplePay: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000010',
  ownPending: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000011',
}

export const SEEDED_PROVIDER_IDS = {
  ownPending: 'mock_pay_settles_after_checks_0011',
  pendingLive: 'mock_pay_initiated_0008',
}

export const financeEntitiesSeed = {
  5001: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity — Riyadh' },
  6001: { ar: 'مؤسسة نماء للتنمية', en: 'Namaa Development Foundation' },
  6002: { ar: 'جمعية إحسان الخيرية', en: 'Ehsan Charitable Society' },
  6003: { ar: 'وقف الخير للرعاية الصحية', en: 'Al-Khair Waqf for Healthcare' },
  6004: { ar: 'جمعية عطاء للأيتام', en: 'Ataa Orphans Society' },
  6005: { ar: 'مؤسسة رواد التعليم', en: 'Ruwad Education Foundation' },
  6006: { ar: 'جمعية تمكين لذوي الإعاقة', en: 'Tamkeen Society for People with Disabilities' },
}

const PRICES = {
  accredited: {
    priceBeforeVat: '1000.00',
    vatRate: '15.00',
    vatAmount: '150.00',
    total: '1150.00',
  },
  bag: { priceBeforeVat: '2500.00', vatRate: '15.00', vatAmount: '375.00', total: '2875.00' },
}

function payment(fields) {
  const pricing = PRICES[fields.purpose]
  return {
    entityId: 5001,
    initiatedByUserId: fields.entityId ?? 5001,
    channel: 'electronic',
    mode: 'live',
    idempotencyKey: null,
    providerIntentId: null,
    providerPaymentId: null,
    method: null,
    cardLast4: null,
    ...pricing,
    currency: 'SAR',
    boundAt: null,
    verifiedAt: null,
    paidAt: null,
    failedAt: null,
    cancelledAt: null,
    rejectedAt: null,
    refundedAt: null,
    latePaidAt: null,
    manuallySettledAt: null,
    failureReason: null,
    failureCode: null,
    failureMessage: null,
    ...fields,
    updatedAt:
      fields.updatedAt ??
      fields.refundedAt ??
      fields.paidAt ??
      fields.failedAt ??
      fields.cancelledAt ??
      fields.createdAt,
  }
}

function minutesAfter(iso, minutes) {
  return new Date(new Date(iso).getTime() + minutes * 60000).toISOString()
}

function paidPayment({
  id,
  reference,
  entityId,
  purpose,
  createdAt,
  method,
  cardLast4,
  mode = 'live',
  providerPaymentId,
}) {
  const paidAt = minutesAfter(createdAt, 4)
  return payment({
    id,
    reference,
    entityId,
    initiatedByUserId: entityId,
    purpose,
    mode,
    method,
    cardLast4,
    providerPaymentId,
    providerIntentId: `mock_pi_${reference.slice(-6)}`,
    status: 'paid',
    createdAt,
    expiresAt: minutesAfter(createdAt, 30),
    boundAt: minutesAfter(createdAt, 3),
    verifiedAt: paidAt,
    paidAt,
  })
}

const EXTRA_PAID = [
  [6001, 'accredited', '2026-09-03T07:20:00Z', 'creditcard', '1881'],
  [6002, 'bag', '2026-09-08T09:05:00Z', 'mada', '5012'],
  [6003, 'accredited', '2026-09-14T11:40:00Z', 'applepay', '7730'],
  [6004, 'accredited', '2026-09-19T06:55:00Z', 'mada', '2264'],
  [6005, 'bag', '2026-09-23T12:10:00Z', 'creditcard', '9047'],
  [6006, 'accredited', '2026-09-29T08:30:00Z', 'mada', '3318'],
  [6001, 'bag', '2026-10-04T10:15:00Z', 'stcpay', null],
  [6002, 'accredited', '2026-10-09T07:45:00Z', 'creditcard', '6625'],
  [6003, 'bag', '2026-10-15T13:00:00Z', 'mada', '4409'],
]

function extraPaid() {
  return EXTRA_PAID.map(([entityId, purpose, createdAt, method, cardLast4], index) => {
    const sequence = 12 + index
    return paidPayment({
      id: `3f0c9a52-6d1e-4b7a-9c21-5a1e${String(sequence).padStart(8, '0')}`,
      reference: `PAY-2026-${String(sequence).padStart(6, '0')}`,
      entityId,
      purpose,
      createdAt,
      method,
      cardLast4,
      providerPaymentId: `mock_pay_paid_${String(sequence).padStart(4, '0')}`,
    })
  })
}

export function paymentsSeed() {
  return [
    paidPayment({
      id: SEEDED_IDS.paidAccredited,
      reference: 'PAY-2026-000001',
      entityId: 5001,
      purpose: 'accredited',
      createdAt: '2026-08-12T08:10:00Z',
      method: 'mada',
      cardLast4: '4821',
      providerPaymentId: 'mock_pay_paid_0001',
    }),
    paidPayment({
      id: SEEDED_IDS.paidBag,
      reference: 'PAY-2026-000002',
      entityId: 6001,
      purpose: 'bag',
      createdAt: '2026-08-20T10:30:00Z',
      method: 'creditcard',
      cardLast4: '1094',
      providerPaymentId: 'mock_pay_paid_0002',
    }),
    {
      ...paidPayment({
        id: SEEDED_IDS.refunded,
        reference: 'PAY-2026-000003',
        entityId: 6002,
        purpose: 'accredited',
        createdAt: '2026-08-25T07:00:00Z',
        method: 'mada',
        cardLast4: '7712',
        providerPaymentId: 'mock_pay_paid_0003',
      }),
      status: 'refunded',
      refundedAt: '2026-09-02T09:20:00Z',
      updatedAt: '2026-09-02T09:20:00Z',
    },
    payment({
      id: SEEDED_IDS.failedLive,
      reference: 'PAY-2026-000004',
      entityId: 6003,
      initiatedByUserId: 6003,
      purpose: 'accredited',
      status: 'failed',
      method: 'creditcard',
      cardLast4: '3390',
      providerPaymentId: 'mock_pay_failed_0004',
      providerIntentId: 'mock_pi_000004',
      createdAt: '2026-09-01T12:00:00Z',
      expiresAt: '2026-09-01T12:30:00Z',
      boundAt: '2026-09-01T12:02:00Z',
      verifiedAt: '2026-09-01T12:03:00Z',
      failedAt: '2026-09-01T12:03:00Z',
      failureReason: 'insufficient_funds',
      failureCode: '51',
      failureMessage: 'Insufficient funds',
    }),
    payment({
      id: SEEDED_IDS.cancelledExpired,
      reference: 'PAY-2026-000005',
      entityId: 6004,
      initiatedByUserId: 6004,
      purpose: 'bag',
      status: 'cancelled',
      providerIntentId: 'mock_pi_000005',
      createdAt: '2026-09-06T08:00:00Z',
      expiresAt: '2026-09-06T08:30:00Z',
      cancelledAt: '2026-09-06T08:30:00Z',
    }),
    paidPayment({
      id: SEEDED_IDS.paidTest,
      reference: 'PAY-2026-000006',
      entityId: 5001,
      purpose: 'bag',
      createdAt: '2026-09-10T09:00:00Z',
      method: 'creditcard',
      cardLast4: '1111',
      mode: 'test',
      providerPaymentId: 'mock_pay_paid_0006',
    }),
    payment({
      id: SEEDED_IDS.latePaid,
      reference: 'PAY-2026-000007',
      entityId: 6005,
      initiatedByUserId: 6005,
      purpose: 'accredited',
      status: 'cancelled',
      method: 'mada',
      cardLast4: '6038',
      providerPaymentId: 'mock_pay_paid_0007',
      providerIntentId: 'mock_pi_000007',
      createdAt: '2026-09-12T14:00:00Z',
      expiresAt: '2026-09-12T14:30:00Z',
      boundAt: '2026-09-12T14:28:00Z',
      cancelledAt: '2026-09-12T14:45:00Z',
      latePaidAt: '2026-09-12T14:52:00Z',
      updatedAt: '2026-09-12T14:52:00Z',
    }),
    payment({
      id: SEEDED_IDS.pendingLive,
      reference: 'PAY-2026-000008',
      entityId: 6006,
      initiatedByUserId: 6006,
      purpose: 'bag',
      status: 'pending',
      providerPaymentId: SEEDED_PROVIDER_IDS.pendingLive,
      providerIntentId: 'mock_pi_000008',
      createdAt: '2026-10-30T10:00:00Z',
      expiresAt: '2026-10-30T10:30:00Z',
      boundAt: '2026-10-30T10:04:00Z',
    }),
    payment({
      id: SEEDED_IDS.ownFailed,
      reference: 'PAY-2026-000009',
      entityId: 5001,
      initiatedByUserId: 5001,
      purpose: 'accredited',
      status: 'failed',
      method: 'mada',
      cardLast4: '4821',
      providerPaymentId: 'mock_pay_failed_0009',
      providerIntentId: 'mock_pi_000009',
      createdAt: '2026-08-11T18:00:00Z',
      expiresAt: '2026-08-11T18:30:00Z',
      boundAt: '2026-08-11T18:03:00Z',
      verifiedAt: '2026-08-11T18:04:00Z',
      failedAt: '2026-08-11T18:04:00Z',
      failureReason: 'declined',
      failureCode: '05',
      failureMessage: 'Declined',
    }),
    paidPayment({
      id: SEEDED_IDS.paidApplePay,
      reference: 'PAY-2026-000010',
      entityId: 6001,
      purpose: 'accredited',
      createdAt: '2026-08-28T13:30:00Z',
      method: 'applepay',
      cardLast4: '5560',
      providerPaymentId: 'mock_pay_paid_0010',
    }),
    payment({
      id: SEEDED_IDS.ownPending,
      reference: 'PAY-2026-000011',
      entityId: 5001,
      initiatedByUserId: 5001,
      purpose: 'bag',
      status: 'pending',
      providerPaymentId: SEEDED_PROVIDER_IDS.ownPending,
      providerIntentId: 'mock_pi_000011',
      createdAt: '2026-10-31T09:00:00Z',
      expiresAt: '2026-10-31T09:30:00Z',
      boundAt: '2026-10-31T09:03:00Z',
    }),
    ...extraPaid(),
  ]
}

export function providerPaymentsSeed() {
  return {
    [SEEDED_PROVIDER_IDS.ownPending]: {
      id: SEEDED_PROVIDER_IDS.ownPending,
      status: 'initiated',
      amount: 287500,
      currency: 'SAR',
      metadata: {
        moashir_payment_id: SEEDED_IDS.ownPending,
        moashir_reference: 'PAY-2026-000011',
        purpose: 'bag',
        app_env: 'local',
      },
      source: { type: 'creditcard', company: 'mada', number: '4201-32XX-XXXX-4821' },
      responseCode: null,
      checksUntilSettled: 2,
      settlesTo: 'paid',
    },
    [SEEDED_PROVIDER_IDS.pendingLive]: {
      id: SEEDED_PROVIDER_IDS.pendingLive,
      status: 'initiated',
      amount: 287500,
      currency: 'SAR',
      metadata: {
        moashir_payment_id: SEEDED_IDS.pendingLive,
        moashir_reference: 'PAY-2026-000008',
        purpose: 'bag',
        app_env: 'local',
      },
      source: { type: 'creditcard', company: 'visa', number: '4012-88XX-XXXX-0091' },
      responseCode: null,
      checksUntilSettled: null,
      settlesTo: null,
    },
  }
}

export const REFUND_EVIDENCE_FILE_ID = '9a4e6b21-0c3d-4f5a-8e7b-1d2c00000001'

export function refundsSeed() {
  return [
    {
      id: 'c51b8e0a-2f47-4d6c-a913-7e0f00000001',
      paymentId: SEEDED_IDS.refunded,
      status: 'recorded',
      amount: '1150.00',
      currency: 'SAR',
      refundedOn: '2026-09-01',
      transferReference: 'TRF-2026-0091',
      reason: 'طلبت الجهة إلغاء التقييم المعتمد قبل بدء المقيّم، وأُعيد المبلغ بتحويل بنكي.',
      recordedBy: { ...FINANCE_STAFF.financeOfficer },
      evidenceFileId: REFUND_EVIDENCE_FILE_ID,
      createdAt: '2026-09-02T09:20:00Z',
    },
  ]
}

export function storedFilesSeed() {
  return [
    {
      id: REFUND_EVIDENCE_FILE_ID,
      purpose: 'refund_evidence',
      name: 'bank-transfer-receipt.pdf',
      mimeType: 'application/pdf',
      sizeBytes: 184320,
      scanStatus: 'clean',
      createdAt: '2026-09-02T09:19:00Z',
    },
    { ...FIXTURE_ENTITY_LOGO_FILE },
  ]
}

export function paymentEventsSeed() {
  return [
    {
      id: 1,
      paymentId: SEEDED_IDS.paidAccredited,
      source: 'checkout',
      eventType: 'intent_created',
      mode: 'live',
      signatureValid: null,
      result: 'applied',
      errorCode: null,
      providerPaymentId: null,
      createdAt: '2026-08-12T08:10:02Z',
      processedAt: '2026-08-12T08:10:02Z',
    },
    {
      id: 2,
      paymentId: SEEDED_IDS.paidAccredited,
      source: 'verification',
      eventType: 'payment_fetched',
      mode: 'live',
      signatureValid: null,
      result: 'applied',
      errorCode: null,
      providerPaymentId: 'mock_pay_paid_0001',
      createdAt: '2026-08-12T08:14:00Z',
      processedAt: '2026-08-12T08:14:00Z',
    },
    {
      id: 3,
      paymentId: SEEDED_IDS.paidAccredited,
      source: 'webhook',
      eventType: 'payment_paid',
      mode: 'live',
      signatureValid: true,
      result: 'ignored',
      errorCode: null,
      providerPaymentId: 'mock_pay_paid_0001',
      createdAt: '2026-08-12T08:14:05Z',
      processedAt: '2026-08-12T08:14:06Z',
    },
    {
      id: 4,
      paymentId: SEEDED_IDS.failedLive,
      source: 'webhook',
      eventType: 'payment_failed',
      mode: 'live',
      signatureValid: true,
      result: 'applied',
      errorCode: null,
      providerPaymentId: 'mock_pay_failed_0004',
      createdAt: '2026-09-01T12:03:04Z',
      processedAt: '2026-09-01T12:03:05Z',
    },
    {
      id: 5,
      paymentId: SEEDED_IDS.cancelledExpired,
      source: 'expiry',
      eventType: 'order_expired',
      mode: 'live',
      signatureValid: null,
      result: 'applied',
      errorCode: null,
      providerPaymentId: null,
      createdAt: '2026-09-06T08:30:01Z',
      processedAt: '2026-09-06T08:30:01Z',
    },
    {
      id: 6,
      paymentId: SEEDED_IDS.refunded,
      source: 'admin',
      eventType: 'refund_recorded',
      mode: 'live',
      signatureValid: null,
      result: 'applied',
      errorCode: null,
      providerPaymentId: null,
      createdAt: '2026-09-02T09:20:00Z',
      processedAt: '2026-09-02T09:20:00Z',
    },
    {
      id: 7,
      paymentId: SEEDED_IDS.latePaid,
      source: 'webhook',
      eventType: 'payment_paid',
      mode: 'live',
      signatureValid: true,
      result: 'late_payment',
      errorCode: null,
      providerPaymentId: 'mock_pay_paid_0007',
      createdAt: '2026-09-12T14:52:00Z',
      processedAt: '2026-09-12T14:52:02Z',
    },
    {
      id: 8,
      paymentId: null,
      source: 'webhook',
      eventType: 'payment_paid',
      mode: 'live',
      signatureValid: true,
      result: 'unmatched',
      errorCode: null,
      providerPaymentId: 'mock_pay_paid_9921',
      createdAt: '2026-10-02T06:41:00Z',
      processedAt: '2026-10-02T06:41:01Z',
    },
    {
      id: 9,
      paymentId: SEEDED_IDS.pendingLive,
      source: 'reconciliation',
      eventType: 'payment_fetched',
      mode: 'live',
      signatureValid: null,
      result: 'pending',
      errorCode: null,
      providerPaymentId: SEEDED_PROVIDER_IDS.pendingLive,
      createdAt: '2026-10-30T10:10:00Z',
      processedAt: '2026-10-30T10:10:00Z',
    },
    {
      id: 10,
      paymentId: null,
      source: 'verification',
      eventType: 'payment_fetched',
      mode: 'test',
      signatureValid: null,
      result: 'rejected',
      errorCode: 'payment.verification_rejected',
      providerPaymentId: 'mock_pay_foreign_3310',
      createdAt: '2026-10-21T11:05:00Z',
      processedAt: '2026-10-21T11:05:00Z',
    },
  ]
}

export function abandonedCartsSeed() {
  return [
    {
      id: 41,
      entityId: 6003,
      email: 'info@alkhair-waqf.example.sa',
      marketingConsent: true,
      purpose: 'accredited',
      startedAt: '2026-10-27T08:15:00Z',
      lastSeenAt: '2026-10-28T10:02:00Z',
      contactedAt: null,
    },
    {
      id: 42,
      entityId: 6004,
      email: 'contact@ataa-orphans.example.sa',
      marketingConsent: false,
      purpose: 'bag',
      startedAt: '2026-10-25T12:40:00Z',
      lastSeenAt: '2026-10-25T12:55:00Z',
      contactedAt: null,
    },
    {
      id: 43,
      entityId: 6005,
      email: 'hello@ruwad-education.example.sa',
      marketingConsent: true,
      purpose: 'bag',
      startedAt: '2026-10-20T07:00:00Z',
      lastSeenAt: '2026-10-21T09:30:00Z',
      contactedAt: '2026-10-23T08:00:00Z',
    },
    {
      id: 44,
      entityId: 6006,
      email: 'office@tamkeen.example.sa',
      marketingConsent: true,
      purpose: 'accredited',
      startedAt: '2026-10-18T15:20:00Z',
      lastSeenAt: '2026-10-19T06:45:00Z',
      contactedAt: null,
    },
  ]
}

export const financeEntityEmails = {
  5001: 'info@albirr-riyadh.example.sa',
  6001: 'contact@namaa-jeddah.example.sa',
  6002: 'info@ehsan-charity.example.sa',
  6003: 'info@alkhair-waqf.example.sa',
  6004: 'contact@ataa-orphans.example.sa',
  6005: 'hello@ruwad-education.example.sa',
  6006: 'office@tamkeen.example.sa',
}
