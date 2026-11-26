import { afterEach, describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AppLayout from '@/components/layout/AppLayout.vue'
import { notificationsService } from '@/services/notificationsService'
import { createTestGlobals } from '@/test-utils'
import { installNavigationState } from '@/router/navigationState'
import { MINIMUM_VISIBLE_MS, SHOW_DELAY_MS } from '@/composables/useDeferredVisibility'

vi.mock('@/services/notificationsService', () => ({
  notificationsService: {
    list: vi.fn(),
    unreadCount: vi.fn(),
    markRead: vi.fn(),
    markAllRead: vi.fn(),
  },
}))

beforeEach(() => {
  vi.clearAllMocks()
  notificationsService.list.mockResolvedValue({ data: [], meta: {}, errors: null })
  notificationsService.unreadCount.mockResolvedValue({ data: { count: 0 }, meta: {}, errors: null })
})

describe('AppLayout', () => {
  it('puts the top bar above a body of side bar and main content slot', async () => {
    const wrapper = mount(AppLayout, {
      slots: { default: '<p class="page">محتوى الشاشة</p>' },
      global: createTestGlobals(),
    })
    await flushPromises()
    expect(wrapper.find('.app-layout > .navbar--app').exists()).toBe(true)
    expect(wrapper.find('.app-layout__body > .sidebar').exists()).toBe(true)
    expect(wrapper.find('main.app-layout__content .page').text()).toBe('محتوى الشاشة')
  })
})

describe('AppLayout page loading', () => {
  afterEach(() => vi.useRealTimers())

  it('covers the content with the waiting page while the next page loads, keeping the shell', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'performance'] })
    const globals = createTestGlobals()
    let arrive
    const slowPage = () => new Promise((done) => (arrive = () => done({ render: () => null })))
    globals.router.addRoute({ path: '/slow-page', component: slowPage })
    installNavigationState(globals.router)
    const wrapper = mount(AppLayout, {
      slots: { default: '<p class="page">محتوى الشاشة</p>' },
      global: globals,
    })
    await flushPromises()

    const navigation = globals.router.push('/slow-page')
    await flushPromises()
    vi.advanceTimersByTime(SHOW_DELAY_MS)
    await flushPromises()
    const main = wrapper.find('main.app-layout__content')
    expect(main.find('.app-loading--overlay').exists()).toBe(true)
    expect(main.attributes('aria-busy')).toBe('true')
    expect(wrapper.find('.app-layout__body > .sidebar').exists()).toBe(true)

    arrive()
    await navigation
    vi.advanceTimersByTime(MINIMUM_VISIBLE_MS)
    await flushPromises()
    expect(main.find('.app-loading--overlay').exists()).toBe(false)
    expect(main.attributes('aria-busy')).toBe('false')
  })
})
