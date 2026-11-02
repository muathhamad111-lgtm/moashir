import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { useFinanceSettings } from '@/composables/useFinanceSettings'
import { financeSettingsService } from '@/services/financeSettingsService'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import { withSetup } from '@/test-utils/withSetup'

vi.mock('@/services/financeSettingsService', () => ({
  financeSettingsService: { get: vi.fn(), update: vi.fn() },
}))

const SETTINGS = {
  currency: 'SAR',
  accreditedPrice: '1000.00',
  bagPrice: '2500.00',
  vatRate: '15.00',
  orderValidityMinutes: 30,
  orderValidityLimits: { min: 5, max: 60 },
  pricing: { accredited: null, bag: null },
  editableFields: ['accredited_price', 'bag_price', 'vat_rate', 'order_validity_minutes'],
}

function rejected(code) {
  return Object.assign(new Error(code), { response: { status: 422 }, normalized: { status: 422, code, fieldErrors: {} } })
}

async function setup(extra = {}) {
  const { api, globals } = withSetup(() => useFinanceSettings())
  useAuthStore(globals.pinia).setUser(sessionUserFor(['system_admin'], extra))
  financeSettingsService.get.mockResolvedValue({ data: SETTINGS })
  await api.load()
  return api
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useFinanceSettings', () => {
  it('sends a partial update of the changed fields only', async () => {
    const api = await setup()
    financeSettingsService.update.mockResolvedValue({ data: { ...SETTINGS, vatRate: '5.00' } })
    api.form.setFieldValue('vatRate', '5')
    await api.submit()
    await flushPromises()
    expect(financeSettingsService.update).toHaveBeenCalledWith({ vatRate: '5.00' })
    expect(api.settings.value.vatRate).toBe('5.00')
  })

  it('asks the Super Admin for a reason up front and resubmits with it', async () => {
    const api = await setup({ isSuperAdmin: true })
    api.form.setFieldValue('bagPrice', '3000')
    await api.submit()
    await flushPromises()
    expect(financeSettingsService.update).not.toHaveBeenCalled()
    expect(api.reasonOpen.value).toBe(true)
    financeSettingsService.update.mockResolvedValue({ data: { ...SETTINGS, bagPrice: '3000.00' } })
    await api.confirmReason('Approved price list for 2027.')
    expect(financeSettingsService.update).toHaveBeenCalledWith({ bagPrice: '3000.00', reason: 'Approved price list for 2027.' })
  })

  it('opens the reason dialog when the server asks for a reason', async () => {
    const api = await setup()
    financeSettingsService.update.mockRejectedValueOnce(rejected('reason_required'))
    api.form.setFieldValue('orderValidityMinutes', '45')
    await api.submit()
    await flushPromises()
    expect(api.reasonOpen.value).toBe(true)
    expect(financeSettingsService.update).toHaveBeenCalledWith({ orderValidityMinutes: 45 })
  })
})
