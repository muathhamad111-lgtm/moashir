import { describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { PRELOAD_ERROR_EVENT, installChunkErrorRecovery } from '@/router/chunkErrorRecovery'
import { createReloadGuard } from '@/utils/reloadGuard'
import { ROUTE_NAMES } from '@/constants/routeNames'

const Page = { render: () => null }

function chunkError(name) {
  return new TypeError(
    `Failed to fetch dynamically imported module: https://moashir.example.sa/assets/${name}-DpmMW1sN.js`,
  )
}

function memoryStorage() {
  const values = new Map()
  return {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, String(value)),
  }
}

function fakeBrowser(path = '/') {
  const url = new URL(path, 'https://moashir.example.sa')
  return Object.assign(new EventTarget(), {
    location: {
      pathname: url.pathname,
      search: url.search,
      hash: url.hash,
      assign: vi.fn(),
      reload: vi.fn(),
    },
  })
}

function preloadEvent(payload) {
  return Object.assign(new Event(PRELOAD_ERROR_EVENT, { cancelable: true }), { payload })
}

function routesFor(browser) {
  return [
    { path: '/', component: Page },
    { path: '/auth/login', name: ROUTE_NAMES.AUTH_LOGIN, component: Page },
    {
      path: '/auth/mfa',
      name: ROUTE_NAMES.AUTH_MFA,
      component: () => Promise.reject(chunkError('Mfa')),
    },
    {
      path: '/auth/otp',
      name: ROUTE_NAMES.AUTH_OTP,
      component: () => Promise.reject(chunkError('Otp')),
    },
    { path: '/requests', name: 'requests', component: () => Promise.reject(chunkError('Index')) },
    {
      path: '/reports',
      name: 'reports',
      component: () => {
        browser.dispatchEvent(preloadEvent(chunkError('Reports')))
        return Promise.reject(chunkError('Reports'))
      },
    },
    {
      path: '/broken',
      name: 'broken',
      component: () => Promise.reject(new Error('Unexpected token in module')),
    },
  ]
}

async function pageLife({ storage = memoryStorage(), path = '/' } = {}) {
  const browser = fakeBrowser(path)
  const router = createRouter({ history: createMemoryHistory(), routes: routesFor(browser) })
  installChunkErrorRecovery(router, {
    browser,
    guard: createReloadGuard({ storage: () => storage }),
  })
  await router.push(path)
  return { router, browser, storage }
}

describe('installChunkErrorRecovery', () => {
  it('loads the target route in full when its chunk is missing after a deploy', async () => {
    const { router, browser } = await pageLife()
    await expect(router.push('/requests?status=submitted')).rejects.toThrow(/dynamically imported/)
    expect(browser.location.assign).toHaveBeenCalledExactlyOnceWith('/requests?status=submitted')
    expect(browser.location.reload).not.toHaveBeenCalled()
  })

  it('restarts sign-in on the login page when the MFA step cannot load, keeping the redirect', async () => {
    const { router, browser } = await pageLife({ path: '/auth/login' })
    await expect(
      router.push({ name: ROUTE_NAMES.AUTH_MFA, query: { redirect: '/requests' } }),
    ).rejects.toThrow(/dynamically imported/)
    expect(browser.location.assign).toHaveBeenCalledExactlyOnceWith(
      '/auth/login?redirect=/requests',
    )
  })

  it('restarts sign-in on the login page when the code step cannot load, dropping an outside redirect', async () => {
    const { router, browser } = await pageLife({ path: '/auth/login' })
    await expect(
      router.push({ name: ROUTE_NAMES.AUTH_OTP, query: { redirect: '//evil.example.com' } }),
    ).rejects.toThrow(/dynamically imported/)
    expect(browser.location.assign).toHaveBeenCalledExactlyOnceWith('/auth/login')
  })

  it('leaves other navigation errors to the existing error handling', async () => {
    const { router, browser } = await pageLife()
    await expect(router.push('/broken')).rejects.toThrow('Unexpected token')
    expect(browser.location.assign).not.toHaveBeenCalled()
    expect(browser.location.reload).not.toHaveBeenCalled()
  })

  it('does not reload the same target again when the fresh page fails too', async () => {
    const storage = memoryStorage()
    const first = await pageLife({ storage })
    await expect(first.router.push('/requests')).rejects.toThrow(/dynamically imported/)
    expect(first.browser.location.assign).toHaveBeenCalledOnce()

    const second = await pageLife({ storage })
    await expect(second.router.push('/requests')).rejects.toThrow(/dynamically imported/)
    expect(second.browser.location.assign).not.toHaveBeenCalled()
  })

  it('reloads the current page when a chunk fails outside a navigation', async () => {
    const { browser } = await pageLife({ path: '/auth/login?redirect=/requests' })
    const event = preloadEvent(chunkError('LandingLayout'))
    browser.dispatchEvent(event)
    expect(browser.location.reload).toHaveBeenCalledOnce()
    expect(event.defaultPrevented).toBe(false)
  })

  it('lets the router recover a chunk that fails during a navigation, once', async () => {
    const { router, browser } = await pageLife()
    await expect(router.push('/reports')).rejects.toThrow(/dynamically imported/)
    expect(browser.location.reload).not.toHaveBeenCalled()
    expect(browser.location.assign).toHaveBeenCalledExactlyOnceWith('/reports')
  })

  it('ignores preload events that are not chunk failures', async () => {
    const { browser } = await pageLife()
    browser.dispatchEvent(preloadEvent(new Error('Network changed')))
    expect(browser.location.reload).not.toHaveBeenCalled()
  })

  it('reloads only once in a page life when session storage is unavailable', async () => {
    const browser = fakeBrowser('/')
    const router = createRouter({ history: createMemoryHistory(), routes: routesFor(browser) })
    const storage = () => {
      throw new DOMException('The operation is insecure.', 'SecurityError')
    }
    installChunkErrorRecovery(router, { browser, guard: createReloadGuard({ storage }) })
    await router.push('/')
    await expect(router.push('/requests')).rejects.toThrow(/dynamically imported/)
    browser.dispatchEvent(preloadEvent(chunkError('LandingLayout')))
    expect(browser.location.assign).toHaveBeenCalledOnce()
    expect(browser.location.reload).not.toHaveBeenCalled()
  })
})
