import { describe, expect, it } from 'vitest'
import {
  evidenceProblem,
  refundFormData,
  refundSchema,
  riyadhDate,
} from '@/utils/finance/refundSchema'
import { manualSettlePayload, manualSettleSchema } from '@/utils/finance/manualSettleSchema'
import {
  changedSettings,
  editableFieldsOf,
  financeSettingsSchema,
  formValuesOf,
  normalizedDecimal,
} from '@/utils/finance/financeSettingsForm'

const t = (key, params) => (params ? `${key}:${JSON.stringify(params)}` : key)

function pdf(name = 'transfer.pdf', size = 8) {
  return new File(['x'.repeat(size)], name, { type: 'application/pdf' })
}

async function errorsOf(schema, values) {
  try {
    await schema.validate(values, { abortEarly: false })
    return {}
  } catch (error) {
    return Object.fromEntries(error.inner.map((item) => [item.path, item.message]))
  }
}

describe('refund form', () => {
  const schema = refundSchema(t, { total: '1150.00', paidOn: '2026-08-12' })
  const valid = {
    amount: '1150.00',
    refundedOn: '2026-09-01',
    reason: 'The entity withdrew before the assessor started.',
    transferReference: 'TRF-2026/0142',
    evidence: pdf(),
  }

  it('accepts a full refund with a transfer receipt', async () => {
    expect(await errorsOf(schema, valid)).toEqual({})
  })

  it('refuses partial amounts, dates outside the payment window, short reasons and bad references', async () => {
    const errors = await errorsOf(schema, {
      amount: '100.00',
      refundedOn: '2026-08-01',
      reason: 'Too short',
      transferReference: 'TR<1>',
      evidence: null,
    })
    expect(Object.keys(errors).sort()).toEqual([
      'amount',
      'evidence',
      'reason',
      'refundedOn',
      'transferReference',
    ])
    expect(errors.amount).toContain('1150.00')
    expect(errors.refundedOn).toBe('finance.validation.dateBeforePayment')
    const future = await errorsOf(schema, { ...valid, refundedOn: '2999-01-01' })
    expect(future.refundedOn).toBe('finance.validation.dateFuture')
  })

  it('checks the evidence type and size before upload', () => {
    expect(evidenceProblem(null)).toBe('required')
    expect(evidenceProblem(pdf('transfer.docx'))).toBe('type')
    expect(evidenceProblem(pdf('scan.JPEG'))).toBeNull()
    const big = pdf('big.pdf')
    Object.defineProperty(big, 'size', { value: 21 * 1024 * 1024 })
    expect(evidenceProblem(big)).toBe('size')
  })

  it('builds the multipart body with trimmed text and the file', () => {
    const form = refundFormData({ ...valid, reason: `  ${valid.reason}  ` })
    expect([...form.keys()]).toEqual([
      'amount',
      'refundedOn',
      'reason',
      'transferReference',
      'evidence',
    ])
    expect(form.get('reason')).toBe(valid.reason)
    expect(form.get('evidence')).toBeInstanceOf(File)
  })

  it('formats today in Asia/Riyadh as YYYY-MM-DD', () => {
    expect(riyadhDate('2026-10-31T22:30:00Z')).toBe('2026-11-01')
  })
})

describe('manual settlement form', () => {
  it('requires a final status and a reason of 20 characters, the gateway id being optional', async () => {
    const schema = manualSettleSchema(t)
    expect(
      await errorsOf(schema, {
        status: 'paid',
        providerPaymentId: '',
        reason: 'Confirmed in the dashboard today.',
      }),
    ).toEqual({})
    const errors = await errorsOf(schema, {
      status: 'refunded',
      providerPaymentId: 'bad id',
      reason: 'short',
    })
    expect(Object.keys(errors).sort()).toEqual(['providerPaymentId', 'reason', 'status'])
  })

  it('sends the gateway id only when given', () => {
    expect(
      manualSettlePayload({ status: 'failed', providerPaymentId: '  ', reason: ' Reason text ' }),
    ).toEqual({ status: 'failed', reason: 'Reason text' })
    expect(
      manualSettlePayload({
        status: 'paid',
        providerPaymentId: 'mock_pay_1',
        reason: 'Reason text',
      }),
    ).toEqual({
      status: 'paid',
      providerPaymentId: 'mock_pay_1',
      reason: 'Reason text',
    })
  })
})

describe('finance settings form', () => {
  const settings = {
    accreditedPrice: '1000.00',
    bagPrice: null,
    vatRate: '15.00',
    orderValidityMinutes: 30,
    orderValidityLimits: { min: 5, max: 60 },
    editableFields: ['accredited_price', 'vat_rate', 'order_validity_minutes'],
  }
  const editable = editableFieldsOf(settings)

  it('reads the editable fields from the server and never lets the client decide', () => {
    expect([...editable]).toEqual(['accreditedPrice', 'vatRate', 'orderValidityMinutes'])
    expect(editableFieldsOf(null).size).toBe(0)
  })

  it('sends only changed, editable fields normalized to the server format', () => {
    const values = {
      ...formValuesOf(settings),
      accreditedPrice: '1500',
      bagPrice: '2000',
      vatRate: '\u0665',
      orderValidityMinutes: '30',
    }
    expect(changedSettings(settings, values, editable)).toEqual({
      accreditedPrice: '1500.00',
      vatRate: '5.00',
    })
    expect(changedSettings(settings, formValuesOf(settings), editable)).toEqual({})
    expect(normalizedDecimal('1500.5')).toBe('1500.50')
  })

  it('validates prices, the VAT rate and the validity window', async () => {
    const schema = financeSettingsSchema(
      t,
      settings.orderValidityLimits,
      new Set(['accreditedPrice', 'bagPrice', 'vatRate', 'orderValidityMinutes']),
    )
    const errors = await errorsOf(schema, {
      accreditedPrice: '12345678',
      bagPrice: '0',
      vatRate: '101',
      orderValidityMinutes: '61',
    })
    expect(Object.keys(errors).sort()).toEqual([
      'accreditedPrice',
      'bagPrice',
      'orderValidityMinutes',
      'vatRate',
    ])
    expect(
      await errorsOf(schema, {
        accreditedPrice: '1500.50',
        bagPrice: '',
        vatRate: '0',
        orderValidityMinutes: '5',
      }),
    ).toEqual({})
  })
})
