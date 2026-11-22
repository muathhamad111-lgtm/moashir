import { describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { defineComponent } from 'vue'
import { warmRouteChunks } from '@/router/routeChunks'

const Eager = defineComponent({ name: 'EagerPage', render: () => null })

function routerWith(lazy) {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/lazy', name: 'lazy', component: lazy },
      { path: '/eager', name: 'eager', component: Eager },
      { path: '/broken', name: 'broken', component: () => Promise.reject(new Error('chunk')) },
    ],
  })
}

describe('warmRouteChunks', () => {
  it('starts loading a lazy route component without navigating', async () => {
    const lazy = vi.fn().mockResolvedValue({ default: Eager })
    const router = routerWith(lazy)
    warmRouteChunks(router, { name: 'lazy' })
    await Promise.resolve()
    expect(lazy).toHaveBeenCalledTimes(1)
    expect(router.currentRoute.value.name).toBeUndefined()
  })

  it('leaves eager components alone and swallows a failed chunk', async () => {
    const router = routerWith(vi.fn())
    expect(() => warmRouteChunks(router, { name: 'eager' })).not.toThrow()
    warmRouteChunks(router, { name: 'broken' })
    await new Promise((resolve) => setTimeout(resolve))
  })
})
