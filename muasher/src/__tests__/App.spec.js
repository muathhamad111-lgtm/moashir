import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import App from '@/App.vue'
import { createTestGlobals } from '@/test-utils'
import { MINIMUM_VISIBLE_MS, SHOW_DELAY_MS } from '@/composables/useDeferredVisibility'
import { ROUTE_NAMES } from '@/constants/routeNames'

function mountApp(locale) {
  const globals = createTestGlobals({ locale })
  const wrapper = mount(App, {
    global: { plugins: globals.plugins, directives: globals.directives },
    attachTo: document.body,
  })
  return { wrapper, router: globals.router }
}

describe('App boot', () => {
  beforeEach(() => vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'performance'] }))
  afterEach(() => vi.useRealTimers())

  it.each(['ar', 'en'])(
    'shows the official waiting page, never the public chrome, until the route resolves (%s)',
    async (locale) => {
      const { wrapper, router } = mountApp(locale)
      expect(wrapper.find('.app-loading--screen').exists()).toBe(true)
      expect(wrapper.find('.public-layout').exists()).toBe(false)
      expect(wrapper.find('.brand-loader').exists()).toBe(false)

      vi.advanceTimersByTime(SHOW_DELAY_MS)
      await flushPromises()
      expect(wrapper.find('.brand-loader').exists()).toBe(true)
      expect(wrapper.find('.public-layout').exists()).toBe(false)

      await router.push({ name: ROUTE_NAMES.AUTH_LOGIN })
      await flushPromises()
      expect(wrapper.find('.auth-layout').exists()).toBe(true)
      expect(wrapper.find('.app-loading--screen').exists()).toBe(true)

      vi.advanceTimersByTime(MINIMUM_VISIBLE_MS)
      await flushPromises()
      expect(wrapper.find('.app-loading--screen').exists()).toBe(false)
      wrapper.unmount()
    },
  )
})
