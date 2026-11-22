import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, useTemplateRef } from 'vue'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { useIdleRouteWarmup, useRouteIntentPrefetch } from '@/composables/useRoutePrefetch'
import { prefetchRegistrationReads } from '@/composables/registration/registrationReads'
import { ROUTE_NAMES } from '@/constants/routeNames'

vi.mock('@/composables/registration/registrationReads', () => ({
  prefetchRegistrationReads: vi.fn(),
}))

const Page = defineComponent({ name: 'StubPage', render: () => null })

function setup() {
  const loaders = {
    register: vi.fn().mockResolvedValue({ default: Page }),
    forgot: vi.fn().mockResolvedValue({ default: Page }),
  }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: ROUTE_NAMES.PUBLIC_HOME, component: Page },
      { path: '/auth/register', name: ROUTE_NAMES.AUTH_REGISTER, component: loaders.register },
      {
        path: '/auth/forgot-password',
        name: ROUTE_NAMES.AUTH_FORGOT_PASSWORD,
        component: loaders.forgot,
      },
    ],
  })
  const Host = defineComponent({
    setup() {
      const root = useTemplateRef('root')
      useRouteIntentPrefetch(root)
      useIdleRouteWarmup([ROUTE_NAMES.AUTH_FORGOT_PASSWORD])
      return () =>
        h('div', { ref: 'root' }, [
          h('a', { href: '/auth/register', class: 'register' }, [h('span', 'register')]),
          h('a', { href: 'https://example.org/auth/register', class: 'external' }, 'out'),
          h('a', { href: '/', class: 'home' }, 'home'),
        ])
    },
  })
  const wrapper = mount(Host, { global: { plugins: [router] }, attachTo: document.body })
  return { wrapper, loaders }
}

describe('useRoutePrefetch', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => {
    vi.useRealTimers()
    vi.clearAllMocks()
    document.body.innerHTML = ''
  })

  it.each(['pointerover', 'focusin', 'touchstart'])(
    'warms the register chunk and its data on %s over a register link',
    async (type) => {
      const { wrapper, loaders } = setup()
      wrapper.find('.register span').element.dispatchEvent(new Event(type, { bubbles: true }))
      await vi.runAllTicks()
      await Promise.resolve()
      expect(loaders.register).toHaveBeenCalled()
      expect(prefetchRegistrationReads).toHaveBeenCalledTimes(1)
      wrapper.unmount()
    },
  )

  it('ignores links to other sites and pages without prefetched data', async () => {
    const { wrapper, loaders } = setup()
    for (const selector of ['.external', '.home'])
      wrapper.find(selector).element.dispatchEvent(new Event('pointerover', { bubbles: true }))
    await Promise.resolve()
    expect(loaders.register).not.toHaveBeenCalled()
    expect(prefetchRegistrationReads).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('warms the sibling chunks once the page is idle, and not after unmount', async () => {
    const first = setup()
    expect(first.loaders.forgot).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(2000)
    expect(first.loaders.forgot).toHaveBeenCalledTimes(1)
    first.wrapper.unmount()

    const second = setup()
    second.wrapper.unmount()
    await vi.advanceTimersByTimeAsync(2000)
    expect(second.loaders.forgot).not.toHaveBeenCalled()
  })
})
