import { describe, expect, it } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { installNavigationState, useNavigationState } from '../navigationState'

const Page = { render: () => null }

function deferredPage() {
  let resolve
  const loaded = new Promise((done) => (resolve = done))
  return { component: () => loaded.then(() => Page), resolve: () => resolve() }
}

async function routerWith(slow) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: Page },
      { path: '/slow', component: slow.component },
      { path: '/old', redirect: '/slow' },
    ],
  })
  installNavigationState(router)
  await router.push('/')
  return router
}

describe('navigation state', () => {
  it('counts a page as loading until its chunk arrives', async () => {
    const slow = deferredPage()
    const router = await routerWith(slow)
    const { isLoadingPage } = useNavigationState()

    const navigation = router.push('/slow')
    await flushPromises()
    expect(isLoadingPage.value).toBe(true)
    slow.resolve()
    await navigation
    expect(isLoadingPage.value).toBe(false)
  })

  it('keeps the page in place for a query-only change', async () => {
    const router = await routerWith(deferredPage())
    const { isLoadingPage } = useNavigationState()

    const navigation = router.push({ path: '/', query: { tab: 'open' } })
    await flushPromises()
    expect(isLoadingPage.value).toBe(false)
    await navigation
  })

  it('follows a redirect to the page it lands on', async () => {
    const slow = deferredPage()
    const router = await routerWith(slow)
    const { isLoadingPage } = useNavigationState()

    const navigation = router.push('/old')
    await flushPromises()
    expect(isLoadingPage.value).toBe(true)
    slow.resolve()
    await navigation
    expect(isLoadingPage.value).toBe(false)
    expect(router.currentRoute.value.path).toBe('/slow')
  })

  it('stops loading when the navigation fails', async () => {
    const router = await routerWith({ component: () => Promise.reject(new Error('chunk')) })
    const { isLoadingPage } = useNavigationState()

    await expect(router.push('/slow')).rejects.toThrow('chunk')
    expect(isLoadingPage.value).toBe(false)
  })
})
