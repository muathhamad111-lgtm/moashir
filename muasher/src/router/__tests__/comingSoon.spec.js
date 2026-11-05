import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { appRoutes } from '@/router/routes'
import { withComingSoonScreens } from '@/router/comingSoon'
import { BUILD_POSTURES } from '@/utils/build/buildPosture'
import { ROUTE_NAMES } from '@/constants/routeNames'
import ComingSoon from '@/views/public/ComingSoon.vue'
import { createTestGlobals } from '@/test-utils'

const DEMO_ONLY = [
  ROUTE_NAMES.BAG_INDEX,
  ROUTE_NAMES.BAG_DETAILS,
  ROUTE_NAMES.BAG_PAY,
  ROUTE_NAMES.ADMIN_SETTINGS_GENERAL,
  ROUTE_NAMES.ADMIN_SETTINGS_ABANDONED_CARTS,
  ROUTE_NAMES.ADMIN_SETTINGS_AI_KNOWLEDGE_BASE,
  ROUTE_NAMES.ASSESSMENT_ADVANCED,
  ROUTE_NAMES.AWARD_CYCLES,
  ROUTE_NAMES.AWARD_COMMITTEE,
  ROUTE_NAMES.AWARD_SECRETARY_DESK,
  ROUTE_NAMES.AWARD_RESULTS_BOARD,
]

const PHASE_ONE = [
  ROUTE_NAMES.APP_DASHBOARD,
  ROUTE_NAMES.ASSESSMENT_ACCREDITED,
  ROUTE_NAMES.REQUESTS_INDEX,
  ROUTE_NAMES.CERTIFICATES_INDEX,
  ROUTE_NAMES.ADMIN_SETTINGS_NOTIFICATIONS,
  ROUTE_NAMES.ADMIN_SETTINGS_AI,
]

function find(routes, name) {
  return routes.find((route) => route.name === name)
}

describe('coming-soon screens of a real-operation build', () => {
  const real = withComingSoonScreens(appRoutes, BUILD_POSTURES.REAL)

  it('replaces every demo-only screen and keeps its guards and title', async () => {
    for (const name of DEMO_ONLY) {
      const original = find(appRoutes, name)
      const replaced = find(real, name)
      expect(replaced.meta.comingSoon).toBe(true)
      expect(replaced.meta.titleKey).toBe(original.meta.titleKey)
      expect(replaced.meta.requiresAuth).toBe(original.meta.requiresAuth)
      expect(replaced.path).toBe(original.path)
      expect(replaced.props).toBeUndefined()
      expect(replaced.beforeEnter).toBeUndefined()
      expect((await replaced.component()).default).toBe(ComingSoon)
    }
  })

  it('keeps the phase 1 screens untouched', () => {
    PHASE_ONE.forEach((name) => expect(find(real, name)).toBe(find(appRoutes, name)))
  })

  it.each([BUILD_POSTURES.DEMO, BUILD_POSTURES.MOCK])(
    'keeps every screen working in the %s posture',
    (posture) => {
      expect(withComingSoonScreens(appRoutes, posture)).toBe(appRoutes)
    },
  )
})

describe('coming-soon view', () => {
  async function mountOn(locale, path) {
    const globals = createTestGlobals({ locale })
    globals.router.addRoute({
      path,
      name: 'test.coming-soon',
      component: ComingSoon,
      meta: { titleKey: 'routes.bag.index.title' },
    })
    await globals.router.push(path)
    const wrapper = mount(ComingSoon, { global: globals })
    await flushPromises()
    return wrapper
  }

  it('names the feature and explains it is coming, in Arabic', async () => {
    const wrapper = await mountOn('ar', '/coming-soon-test')
    expect(wrapper.find('h1').text()).not.toBe('')
    expect(wrapper.text()).toContain('قريبًا')
    expect(wrapper.find('a').attributes('href')).toBe('/')
  })

  it('speaks English in the English interface', async () => {
    const wrapper = await mountOn('en', '/coming-soon-test')
    expect(wrapper.text()).toContain('Coming soon')
    expect(wrapper.text()).toContain('Back to the home page')
  })
})
