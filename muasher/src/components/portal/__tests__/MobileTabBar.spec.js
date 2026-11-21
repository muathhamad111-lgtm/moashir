import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import MobileTabBar from '@/components/portal/MobileTabBar.vue'
import { dashboardService } from '@/services/dashboardService'
import { createTestGlobals } from '@/test-utils'
import { stubMatchMedia } from '@/test-utils/matchMedia'

vi.mock('@/services/dashboardService', () => ({ dashboardService: { getEntityBoard: vi.fn() } }))

const PATHS = {
  self: {
    pricing: { price: null, isFree: true, availability: 'available' },
    openDraft: null,
    lastResult: null,
  },
  accredited: {
    pricing: { price: null, isFree: false, availability: 'paused' },
    isLive: true,
    periodStatus: 'not-submitted',
    currentPeriod: null,
    nextPeriod: null,
    awaitingPayment: null,
    openRequest: null,
    lastResult: null,
    kit: { status: null, requestId: null },
  },
  award: {
    pricing: { price: null, isFree: true, availability: 'available' },
    windowState: 'none',
    cycle: null,
    eligibility: { eligible: false, reason: 'no_cycle' },
    openRequest: null,
  },
}

async function at(path) {
  const globals = createTestGlobals()
  await globals.router.push(path)
  return mount(MobileTabBar, { global: globals, attachTo: document.body })
}

afterEach(() => vi.unstubAllGlobals())

describe('MobileTabBar', () => {
  it('offers journey, paths, certificates and more', async () => {
    const wrapper = await at('/dashboard')
    expect(wrapper.findAll('.mobile-tab-bar__label').map((label) => label.text())).toEqual([
      'رحلتي',
      'المسارات',
      'شهاداتي',
      'المزيد',
    ])
    expect(wrapper.find('a.is-active').attributes('aria-current')).toBe('page')
    expect(wrapper.find('a.is-active').text()).toBe('رحلتي')
    wrapper.unmount()
  })

  it('keeps «المزيد» active for payments, messages and notifications', async () => {
    for (const path of ['/payments', '/messages', '/notifications']) {
      const wrapper = await at(path)
      expect(wrapper.find('.is-active').text()).toBe('المزيد')
      wrapper.unmount()
    }
  })

  it('opens the paths sheet with one live status line per path (§19.7)', async () => {
    stubMatchMedia({ width: 390 })
    dashboardService.getEntityBoard.mockResolvedValue({ data: { paths: PATHS } })
    const wrapper = await at('/dashboard')
    const tab = wrapper.find('button[aria-haspopup="dialog"]')
    expect(tab.text()).toContain('المسارات')
    await tab.trigger('click')
    await flushPromises()
    expect(wrapper.find('.dropdown-sheet__title').text()).toBe('مسارات التقييم')
    const rows = wrapper.findAll('.paths-sheet__row')
    expect(rows.map((row) => row.attributes('href'))).toEqual([
      '/paths/self',
      '/paths/accredited',
      '/paths/award',
    ])
    expect(rows[0].find('.paths-sheet__status').text()).toBe('لا يوجد تقييم ذاتي بعد')
    expect(rows[1].find('.paths-sheet__status').text()).toBe('غير متاح حالياً')
    await wrapper.find('.dropdown-sheet').trigger('keydown', { key: 'Escape' })
    await flushPromises()
    expect(wrapper.find('.dropdown-sheet').exists()).toBe(false)
    expect(document.activeElement).toBe(tab.element)
    wrapper.unmount()
  })
})
