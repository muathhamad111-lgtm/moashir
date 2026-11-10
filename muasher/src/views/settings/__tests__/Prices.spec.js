import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import Prices from '@/views/settings/Prices.vue'
import { pricesSettingsService } from '@/services/pricesSettingsService'
import { toPriceRow, toPricesSettings } from '@/services/mappers/prices'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/pricesSettingsService', () => ({
  pricesSettingsService: {
    get: vi.fn(),
    history: vi.fn(),
    update: vi.fn(),
    cancelScheduled: vi.fn(),
  },
}))

function quote(price, total) {
  return { priceBeforeVat: price, vatRate: '15.00', vatAmount: '0.00', total, currency: 'SAR' }
}

function version(id, price, extra = {}) {
  return {
    id,
    priceBeforeVat: price,
    currency: 'SAR',
    availability: 'available',
    effectiveFrom: '2026-01-01T00:00:00+00:00',
    effectiveOn: '2026-01-01',
    isScheduled: false,
    setBy: null,
    createdAt: '2026-01-01T00:00:00+00:00',
    quote: quote(price, price),
    ...extra,
  }
}

function row(key, inForce, extra = {}) {
  return {
    key,
    pricing: { price: null, isFree: true, availability: 'available' },
    inForce,
    scheduled: null,
    historyTotal: 1,
    ...extra,
  }
}

const SETTINGS = toPricesSettings({
  currency: 'SAR',
  vatRate: '15.00',
  rows: [
    row('self', version(1, '0.00')),
    row('accredited', version(2, '1000.00'), {
      scheduled: version(5, '1200.00', { isScheduled: true, effectiveOn: '2026-12-01' }),
    }),
    row('award', version(3, '0.00')),
    row('bag', version(4, '2500.00')),
  ],
  limits: {
    priceMax: '9999999.99',
    effectiveFromMin: '2026-11-11',
    effectiveFromMax: '2027-11-10',
  },
})

let wrapper

async function mountAs(roles = ['system_admin'], extra = {}) {
  const globals = createTestGlobals({ locale: 'ar' })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles, { id: 1, ...extra }))
  wrapper = mount(Prices, { global: globals, attachTo: document.body })
  await flushPromises()
  return wrapper
}

function rowEl(key) {
  return wrapper.find(`[data-testid="price-row-${key}"]`)
}

async function submitRow(key, price, settled) {
  await rowEl(key).find('input[name="priceBeforeVat"]').setValue(price)
  await rowEl(key).find('form').trigger('submit')
  await vi.waitFor(async () => {
    await flushPromises()
    settled()
  })
}

function updated() {
  expect(pricesSettingsService.update).toHaveBeenCalled()
}

beforeEach(() => {
  vi.clearAllMocks()
  pricesSettingsService.get.mockResolvedValue({ data: SETTINGS })
})

afterEach(() => wrapper?.unmount())

describe('«أسعار المسارات» card', () => {
  it('shows the four rows with the price in force, the scheduled version and the free label', async () => {
    await mountAs()
    expect(wrapper.findAll('.path-price-row')).toHaveLength(4)
    expect(rowEl('self').find('.path-price__free').text()).toBe('مجاني')
    expect(rowEl('accredited').find('[data-testid="price-scheduled-accredited"]').text()).toContain(
      'السعر المجدول',
    )
    expect(rowEl('bag').find('input[name="priceBeforeVat"]').element.value).toBe('2500.00')
  })

  it('saves one row with its base version and replaces it with the server row', async () => {
    const saved = toPriceRow(row('bag', version(9, '3000.00'), { historyTotal: 2 }))
    pricesSettingsService.update.mockResolvedValue({ data: saved, meta: { changed: true } })
    await mountAs()
    await submitRow('bag', '3000', updated)
    expect(pricesSettingsService.update).toHaveBeenCalledWith('bag', {
      priceBeforeVat: '3000.00',
      availability: 'available',
      effectiveFrom: null,
      baseVersionId: 4,
    })
    expect(rowEl('bag').find('[data-testid="price-history-toggle-bag"]').text()).toContain('(2)')
  })

  it('explains a stale version and shows the current row from the conflict', async () => {
    const current = toPriceRow(row('bag', version(11, '2800.00')))
    pricesSettingsService.update.mockRejectedValue({
      normalized: { status: 409, code: 'prices.stale_version', meta: { row: current } },
    })
    await mountAs()
    await submitRow('bag', '3000', () =>
      expect(rowEl('bag').find('[data-testid="price-stale"]').exists()).toBe(true),
    )
    expect(rowEl('bag').find('[data-testid="price-stale"]').text()).toContain('غيّر مستخدم آخر')
    expect(rowEl('bag').find('input[name="priceBeforeVat"]').element.value).toBe('2800.00')
  })

  it('validates the price before calling the server', async () => {
    await mountAs()
    await submitRow('self', '12.345', () =>
      expect(rowEl('self').text()).toContain('أدخل رقماً بخانتين عشريتين على الأكثر.'),
    )
    expect(pricesSettingsService.update).not.toHaveBeenCalled()
  })

  it('opens the history with who, before and after, and cancels the scheduled price', async () => {
    pricesSettingsService.history.mockResolvedValue({
      data: [
        {
          ...version(5, '1200.00', { isScheduled: true }),
          setBy: { id: 1, name: 'مدير النظام' },
          state: 'scheduled',
          cancelledAt: null,
          cancelledBy: null,
          before: { priceBeforeVat: '1000.00', availability: 'available' },
        },
      ],
      meta: { pagination: { page: 1, lastPage: 1 } },
    })
    pricesSettingsService.cancelScheduled.mockResolvedValue({
      data: toPriceRow(row('accredited', version(2, '1000.00'))),
    })
    await mountAs()
    await rowEl('accredited')
      .find('[data-testid="price-history-toggle-accredited"]')
      .trigger('click')
    await flushPromises()
    const history = rowEl('accredited').find('[data-testid="price-history-accredited"]')
    expect(pricesSettingsService.history).toHaveBeenCalledWith('accredited', {
      page: 1,
      perPage: 10,
    })
    expect(history.text()).toContain('مدير النظام')
    expect(history.text()).toContain('مجدول')
    await rowEl('accredited').find('[data-testid="price-cancel-accredited"]').trigger('click')
    await flushPromises()
    expect(pricesSettingsService.cancelScheduled).toHaveBeenCalledWith('accredited', {})
    expect(rowEl('accredited').find('[data-testid="price-scheduled-accredited"]').exists()).toBe(
      false,
    )
  })

  it('shows the forbidden state to a role without settings.prices.manage', async () => {
    pricesSettingsService.get.mockRejectedValue({ normalized: { status: 403, message: 'x' } })
    await mountAs(['finance_officer'])
    expect(wrapper.find('.path-price-row').exists()).toBe(false)
    expect(wrapper.find('.empty-state').exists()).toBe(true)
  })
})
