import { EXAMPLE_IBAN } from '@/constants/bankTransfer'
import { FINANCE_STAFF } from './finance'

export const EXAMPLE_ACCOUNT_ID = '7c2d9e41-5b3a-4f6e-9d18-2a6b00000001'

export const BANK_TRANSFER_IDS = {
  underReviewAccredited: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000021',
  underReviewBag: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000022',
  underReviewLatest: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000023',
  rejectedAccredited: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000024',
  paidBag: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000025',
}

const PROOF_IDS = {
  underReviewAccredited: '9a4e6b21-0c3d-4f5a-8e7b-1d2c00000021',
  underReviewBag: '9a4e6b21-0c3d-4f5a-8e7b-1d2c00000022',
  underReviewLatest: '9a4e6b21-0c3d-4f5a-8e7b-1d2c00000023',
  rejectedAccredited: '9a4e6b21-0c3d-4f5a-8e7b-1d2c00000024',
  paidBag: '9a4e6b21-0c3d-4f5a-8e7b-1d2c00000025',
}

const SHARED_PROOF_HASH = 'mock-sha256-shared-receipt-0024'

export function bankAccountsSeed() {
  return [
    {
      id: EXAMPLE_ACCOUNT_ID,
      bankName: { ar: 'بنك المثال (مثال)', en: 'Example Bank (example)' },
      accountHolderName: { ar: 'منصة مؤشر — حساب الرسوم', en: 'Moashir Platform — fees account' },
      iban: EXAMPLE_IBAN,
      accountNumber: null,
      payerNote: {
        ar: 'حوّل من الحساب البنكي الرسمي للجهة.',
        en: "Transfer from the entity's official bank account.",
      },
      displayOrder: 1,
      isActive: true,
      activatedAt: '2026-10-20T07:00:00Z',
      deactivatedAt: null,
      archivedAt: null,
      createdAt: '2026-10-20T07:00:00Z',
      createdBy: { ...FINANCE_STAFF.systemAdmin },
    },
  ]
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

function transferPayment({ id, sequence, entityId, purpose, status, submittedAt, decidedAt }) {
  return {
    id,
    reference: `PAY-2026-${String(sequence).padStart(6, '0')}`,
    entityId,
    initiatedByUserId: entityId,
    purpose,
    channel: 'bank_transfer',
    mode: 'test',
    idempotencyKey: null,
    providerIntentId: null,
    providerPaymentId: null,
    method: null,
    cardLast4: null,
    ...PRICES[purpose],
    currency: 'SAR',
    status,
    expiresAt: null,
    createdAt: submittedAt,
    updatedAt: decidedAt ?? submittedAt,
    boundAt: null,
    verifiedAt: null,
    paidAt: status === 'paid' ? decidedAt : null,
    failedAt: null,
    cancelledAt: null,
    rejectedAt: status === 'rejected' ? decidedAt : null,
    refundedAt: null,
    latePaidAt: null,
    manuallySettledAt: null,
    failureReason: null,
    failureCode: null,
    failureMessage: null,
  }
}

const SEEDS = [
  {
    key: 'underReviewAccredited',
    sequence: 21,
    entityId: 6003,
    purpose: 'accredited',
    status: 'under-review',
    submittedAt: '2026-11-03T06:20:00Z',
    proof: { name: 'bank-transfer-receipt.pdf', mimeType: 'application/pdf', sizeBytes: 839680 },
  },
  {
    key: 'underReviewBag',
    sequence: 22,
    entityId: 6002,
    purpose: 'bag',
    status: 'under-review',
    submittedAt: '2026-11-04T10:05:00Z',
    proof: { name: 'transfer-receipt.png', mimeType: 'image/png', sizeBytes: 1468006 },
    sha256: SHARED_PROOF_HASH,
  },
  {
    key: 'underReviewLatest',
    sequence: 23,
    entityId: 6004,
    purpose: 'bag',
    status: 'under-review',
    submittedAt: '2026-11-05T08:42:00Z',
    proof: { name: 'bank-transfer-receipt.jpg', mimeType: 'image/jpeg', sizeBytes: 1258291 },
  },
  {
    key: 'rejectedAccredited',
    sequence: 24,
    entityId: 6005,
    purpose: 'accredited',
    status: 'rejected',
    submittedAt: '2026-11-01T09:10:00Z',
    decidedAt: '2026-11-02T07:05:00Z',
    proof: { name: 'bank-transfer-receipt.pdf', mimeType: 'application/pdf', sizeBytes: 524288 },
    sha256: SHARED_PROOF_HASH,
    rejection: {
      reason: 'not_received',
      note: 'لم يصل المبلغ إلى الحساب حتى الآن. إن حوّلت المبلغ فأرفق الإيصال نفسه في الطلب الجديد.',
    },
  },
  {
    key: 'paidBag',
    sequence: 25,
    entityId: 6005,
    purpose: 'bag',
    status: 'paid',
    submittedAt: '2026-10-28T08:30:00Z',
    decidedAt: '2026-10-29T06:15:00Z',
    proof: { name: 'bank-transfer-receipt.pdf', mimeType: 'application/pdf', sizeBytes: 614400 },
  },
]

export function bankTransferPaymentsSeed() {
  return SEEDS.map((seed) =>
    transferPayment({ ...seed, id: BANK_TRANSFER_IDS[seed.key], decidedAt: seed.decidedAt }),
  )
}

export function transferProofFilesSeed() {
  return SEEDS.map((seed) => ({
    id: PROOF_IDS[seed.key],
    purpose: 'transfer_proof',
    ...seed.proof,
    scanStatus: 'clean',
    sha256: seed.sha256 ?? `mock-sha256-${seed.key}`,
    createdAt: seed.submittedAt,
  }))
}

export function transfersSeed() {
  return SEEDS.map((seed) => ({
    paymentId: BANK_TRANSFER_IDS[seed.key],
    bankAccountId: EXAMPLE_ACCOUNT_ID,
    proofFileId: PROOF_IDS[seed.key],
    submittedAt: seed.submittedAt,
    submittedBy: seed.entityId,
    reviewedAt: seed.decidedAt ?? null,
    reviewedBy: seed.decidedAt ? { ...FINANCE_STAFF.financeOfficer } : null,
    rejectionReason: seed.rejection?.reason ?? null,
    reviewNote: seed.rejection?.note ?? null,
  }))
}
