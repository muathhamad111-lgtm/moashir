import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { withSetup } from '@/test-utils/withSetup'
import { useRefundForm } from '@/composables/useRefundForm'
import { financeService } from '@/services/financeService'
import { UPLOAD_STATES } from '@/constants/finance'

vi.mock('@/services/financeService', () => ({ financeService: { recordRefund: vi.fn() } }))

const TRANSACTION = { id: 'p-1', paidAt: '2026-11-01T09:00:00Z', pricing: { total: '2875.00' } }
const RECEIPT = new File(['%PDF-1.7'], 'receipt.pdf', { type: 'application/pdf' })

function refusedWith(code) {
  return {
    response: { status: 422 },
    normalized: { code, message: code, fieldErrors: { evidence: [code] } },
  }
}

async function submitRefusedWith(code, locale) {
  financeService.recordRefund.mockRejectedValueOnce(refusedWith(code))
  const { api, globals } = withSetup(
    () => useRefundForm(ref(TRANSACTION), { onRecorded: vi.fn() }),
    { locale },
  )
  api.form.setValues({
    reason: 'The entity paid twice for the same accredited request.',
    transferReference: 'TRF-2026-55871',
    evidence: RECEIPT,
  })
  await api.submit()
  await flushPromises()
  return { api, t: globals.i18n.global.t }
}

beforeEach(() => vi.clearAllMocks())

describe('refund evidence refusals', () => {
  it.each(['ar', 'en'])(
    'marks a PDF locked with a password as refused with its own message (%s)',
    async (locale) => {
      const { api, t } = await submitRefusedWith('file.password_protected', locale)
      expect(financeService.recordRefund).toHaveBeenCalledOnce()
      expect(api.uploadState.value).toBe(UPLOAD_STATES.REFUSED)
      expect(api.refusal.value).toBe(t('finance.errors.file.password_protected'))
    },
  )

  it('marks a file that could not be checked as refused, to be tried again', async () => {
    const { api, t } = await submitRefusedWith('file.check_failed', 'en')
    expect(api.uploadState.value).toBe(UPLOAD_STATES.REFUSED)
    expect(api.refusal.value).toBe(t('finance.errors.file.check_failed'))
  })
})
