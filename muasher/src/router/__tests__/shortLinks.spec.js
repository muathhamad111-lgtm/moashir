import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { routes } from '@/router/routes'
import { ROUTE_NAMES } from '@/constants/routeNames'

function routerAt() {
  return createRouter({ history: createMemoryHistory(), routes })
}

beforeEach(() => setActivePinia(createPinia()))

describe('short links used by the published home content', () => {
  it.each([
    ['/register', ROUTE_NAMES.AUTH_REGISTER],
    ['/login', ROUTE_NAMES.AUTH_LOGIN],
    ['/accredited', ROUTE_NAMES.ASSESSMENT_ACCREDITED],
  ])('redirects %s to its screen', async (path, name) => {
    const router = routerAt()
    await router.push(path)
    expect(router.currentRoute.value.name).toBe(name)
  })
})
