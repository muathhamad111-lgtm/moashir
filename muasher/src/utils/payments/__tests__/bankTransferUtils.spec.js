import { describe, expect, it } from 'vitest'
import {
  IBAN_ISSUES,
  formatIban,
  ibanChecksumValid,
  ibanIssue,
  ibanLast4,
  isExampleIban,
  normalizeIban,
} from '@/utils/payments/iban'
import { isImageProof, isPdfProof, proofIssue } from '@/utils/payments/proofFile'
import {
  bankAccountPayload,
  bankAccountSchema,
  payerNotePayload,
  rejectionSchema,
} from '@/utils/payments/bankTransferSchemas'
import { PROOF_STATES } from '@/constants/bankTransfer'

const EXAMPLE = 'SA0380000000608010167519'
const t = (key, params) => (params ? `${key}:${JSON.stringify(params)}` : key)

function fileOf(name, type, size = 2048) {
  return { name, type, size }
}

async function errorsOf(schema, values) {
  try {
    await schema.validate(values, { abortEarly: false })
    return {}
  } catch (error) {
    return Object.fromEntries(error.inner.map((issue) => [issue.path, issue.message]))
  }
}

describe('IBAN helpers', () => {
  it('normalizes spaces and case, groups by four and keeps the last four', () => {
    expect(normalizeIban(' sa03 8000 0000 6080 1016 7519 ')).toBe(EXAMPLE)
    expect(formatIban('sa0380000000608010167519')).toBe('SA03 8000 0000 6080 1016 7519')
    expect(formatIban('SA0380000000608010167519999')).toBe('SA03 8000 0000 6080 1016 7519')
    expect(formatIban('sa03 80')).toBe('SA03 80')
    expect(ibanLast4(EXAMPLE)).toBe('7519')
    expect(isExampleIban('sa03 8000 0000 6080 1016 7519')).toBe(true)
  })

  it('accepts the registry example and fictional bank-code 00 numbers', () => {
    expect(ibanIssue(EXAMPLE)).toBeNull()
    expect(ibanIssue('SA53 0000 0000 0012 3456 7890')).toBeNull()
    expect(ibanChecksumValid(EXAMPLE)).toBe(true)
  })

  it('names the first issue of a wrong number', () => {
    expect(ibanIssue('')).toBe(IBAN_ISSUES.REQUIRED)
    expect(ibanIssue('SA03-8000')).toBe(IBAN_ISSUES.CHARACTERS)
    expect(ibanIssue('AE0380000000608010167519')).toBe(IBAN_ISSUES.COUNTRY)
    expect(ibanIssue('SA03 8000 0000 6080')).toBe(IBAN_ISSUES.LENGTH)
    expect(ibanIssue('SA03AB000000608010167519')).toBe(IBAN_ISSUES.FORMAT)
    expect(ibanIssue('SA0380000000608010167518')).toBe(IBAN_ISSUES.CHECKSUM)
  })

  it('rejects every single-digit change of the example', () => {
    const mutations = Array.from(EXAMPLE.slice(2)).map((digit, index) => {
      const next = digit === '9' ? '8' : String(Number(digit) + 1)
      return `SA${EXAMPLE.slice(2, index + 2)}${next}${EXAMPLE.slice(index + 3)}`
    })
    expect(mutations.every((value) => ibanIssue(value) !== null)).toBe(true)
  })
})

describe('receipt file rules', () => {
  it('accepts PDF, JPG and PNG up to 20 MB', () => {
    expect(proofIssue(fileOf('receipt.pdf', 'application/pdf'))).toBeNull()
    expect(proofIssue(fileOf('receipt.JPG', 'image/jpeg'))).toBeNull()
    expect(proofIssue(fileOf('receipt.png', 'image/png', 20 * 1048576))).toBeNull()
  })

  it('refuses other types and larger files', () => {
    expect(proofIssue(fileOf('receipt.docx', ''))).toBe(PROOF_STATES.WRONG_TYPE)
    expect(proofIssue(fileOf('receipt.pdf', 'text/plain'))).toBe(PROOF_STATES.WRONG_TYPE)
    expect(proofIssue(fileOf('receipt.pdf', 'application/pdf', 20 * 1048576 + 1))).toBe(
      PROOF_STATES.TOO_LARGE,
    )
    expect(proofIssue(null)).toBeNull()
  })

  it('tells PDF and image receipts apart', () => {
    expect(isPdfProof({ mimeType: 'application/pdf' })).toBe(true)
    expect(isImageProof({ mimeType: 'image/png' })).toBe(true)
    expect(isImageProof({ mimeType: 'application/pdf' })).toBe(false)
  })
})

describe('bank-transfer schemas', () => {
  it('requires a preset, and a note of 10 characters or more for another reason', async () => {
    expect(await errorsOf(rejectionSchema(t), { rejectionReason: '', note: '' })).toEqual({
      rejectionReason: 'finance.transfers.reject.reasonRequired',
    })
    expect(await errorsOf(rejectionSchema(t), { rejectionReason: 'other', note: '' })).toEqual({
      note: 'finance.transfers.reject.noteRequired',
    })
    expect(
      await errorsOf(rejectionSchema(t), { rejectionReason: 'unreadable', note: 'short' }),
    ).toEqual({ note: 'finance.transfers.reject.noteMin:{"min":10}' })
    expect(await errorsOf(rejectionSchema(t), { rejectionReason: 'unreadable', note: '' })).toEqual(
      {},
    )
  })

  it('asks the Super Admin for a reason only', async () => {
    const values = { rejectionReason: 'duplicate', note: '', reason: '' }
    expect(await errorsOf(rejectionSchema(t, { asksReason: true }), values)).toHaveProperty(
      'reason',
    )
    expect(await errorsOf(rejectionSchema(t), values)).toEqual({})
  })

  it('validates the new account and builds the payload', async () => {
    const values = {
      bankName: { ar: 'مصرف العيّنة', en: 'Sample Bank' },
      accountHolderName: { ar: 'منصة مؤشر', en: 'Moashir Platform' },
      iban: 'SA03 8000 0000 6080 1016 7518',
      accountNumber: '12-34',
      payerNote: { ar: 'حوّل من حساب الجهة', en: '' },
    }
    const errors = await errorsOf(bankAccountSchema(t), values)
    expect(Object.keys(errors).sort()).toEqual(['accountNumber', 'iban', 'payerNote.en'])
    expect(
      bankAccountPayload({
        ...values,
        iban: 'sa03 8000 0000 6080 1016 7519',
        accountNumber: '',
        payerNote: { ar: '', en: '' },
      }),
    ).toEqual({
      bankName: values.bankName,
      accountHolderName: values.accountHolderName,
      iban: EXAMPLE,
    })
    expect(payerNotePayload({ payerNote: { ar: ' ', en: '' } })).toBeNull()
  })
})
