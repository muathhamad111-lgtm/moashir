import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AppLoadingScreen from '../AppLoadingScreen.vue'
import PageLoading from '../PageLoading.vue'
import { createTestGlobals } from '@/test-utils'
import { SHOW_DELAY_MS } from '@/composables/useDeferredVisibility'

describe('AppLoadingScreen', () => {
  it.each([
    ['ar', 'جارٍ التحميل…'],
    ['en', 'Loading…'],
  ])('announces the wait to screen readers in %s', (locale, label) => {
    const wrapper = mount(AppLoadingScreen, {
      props: { variant: 'screen' },
      global: { plugins: createTestGlobals({ locale }).plugins },
    })
    const screen = wrapper.find('[data-testid="app-loading"]')
    expect(screen.classes()).toContain('app-loading--screen')
    expect(screen.attributes('role')).toBe('status')
    expect(screen.attributes('aria-busy')).toBe('true')
    expect(wrapper.find('.app-loading__label').text()).toBe(label)
    expect(wrapper.find('.brand-loader').attributes('aria-hidden')).toBe('true')
  })

  it('keeps its place without the mark while the wait is still short', () => {
    const wrapper = mount(AppLoadingScreen, {
      props: { showsMark: false },
      global: { plugins: createTestGlobals().plugins },
    })
    expect(wrapper.classes()).toContain('app-loading--page')
    expect(wrapper.find('.brand-loader').exists()).toBe(false)
  })
})

describe('PageLoading', () => {
  beforeEach(() => vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'performance'] }))
  afterEach(() => vi.useRealTimers())

  it('shows the mark only once the first data outlasts the delay', async () => {
    const wrapper = mount(PageLoading, { global: { plugins: createTestGlobals().plugins } })
    expect(wrapper.find('.brand-loader').exists()).toBe(false)
    vi.advanceTimersByTime(SHOW_DELAY_MS)
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.brand-loader').exists()).toBe(true)
  })
})
