import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { appRoutes } from '@/router/routes'
import { withComingSoonScreens } from '@/router/comingSoon'
import { BUILD_POSTURES } from '@/utils/build/buildPosture'
import { ROUTE_NAMES } from '@/constants/routeNames'
import ComingSoon from '@/views/public/ComingSoon.vue'
import { createTestGlobals } from '@/test-utils'

const AWAITING_BACKEND = [
  ROUTE_NAMES.BAG_INDEX,
  ROUTE_NAMES.BAG_DETAILS,
  ROUTE_NAMES.BAG_PAY,
  ROUTE_NAMES.BAG_MANAGE,
  ROUTE_NAMES.BAG_COMPARISON,
  ROUTE_NAMES.BAG_REQUESTS,
  ROUTE_NAMES.BAG_EVALUATOR_REQUESTS,
  ROUTE_NAMES.ADMIN_SETTINGS_GENERAL,
  ROUTE_NAMES.ADMIN_SETTINGS_AI_KNOWLEDGE_BASE,
]

const DEMO_ONLY = [ROUTE_NAMES.ADMIN_SETTINGS_ABANDONED_CARTS]

const AWARD_SCREENS = [
  ROUTE_NAMES.ASSESSMENT_ADVANCED,
  ROUTE_NAMES.ASSESSMENT_ADVANCED_FORM,
  ROUTE_NAMES.ASSESSMENT_ADVANCED_PAY,
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

async function shapeOf(routes, name) {
  const route = find(routes, name)
  return {
    comingSoon: route.meta.comingSoon,
    titleKey: route.meta.titleKey,
    requiresAuth: route.meta.requiresAuth,
    path: route.path,
    props: route.props,
    beforeEnter: route.beforeEnter,
    component: (await route.component()).default,
  }
}

function comingSoonShapeOf(name) {
  const original = find(appRoutes, name)
  return {
    comingSoon: true,
    titleKey: original.meta.titleKey,
    requiresAuth: original.meta.requiresAuth,
    path: original.path,
    props: undefined,
    beforeEnter: undefined,
    component: ComingSoon,
  }
}

function replacedAmong(routes, names) {
  return names.filter((name) => find(routes, name) !== find(appRoutes, name))
}

describe('route markers', () => {
  it('marks exactly the screens of the modules awaiting their backend', () => {
    const marked = appRoutes.filter((route) => route.meta?.awaitsBackend).map(({ name }) => name)
    expect(marked.sort()).toEqual([...AWAITING_BACKEND].sort())
  })

  it('never marks a screen both ways and carries no data-module list', () => {
    appRoutes.forEach((route) => {
      expect(route.meta?.awaitsBackend && route.meta?.demoOnly).toBeFalsy()
      expect(route.meta?.demoModules).toBeUndefined()
    })
  })
})

describe.each([BUILD_POSTURES.DEMO, BUILD_POSTURES.REAL])(
  'coming-soon screens of a %s build',
  (posture) => {
    const built = withComingSoonScreens(appRoutes, posture)

    it('lands every module awaiting its backend on the coming-soon screen', async () => {
      for (const name of AWAITING_BACKEND) {
        expect(await shapeOf(built, name)).toEqual(comingSoonShapeOf(name))
      }
    })

    it('keeps the phase 1 screens untouched', () => {
      expect(replacedAmong(built, PHASE_ONE)).toEqual([])
    })
  },
)

describe('demo-only screens', () => {
  it('are coming-soon screens in a real-operation build', async () => {
    const real = withComingSoonScreens(appRoutes, BUILD_POSTURES.REAL)
    for (const name of DEMO_ONLY) {
      expect(await shapeOf(real, name)).toEqual(comingSoonShapeOf(name))
    }
  })

  it('no longer include the award screens, served by the backend in every build', () => {
    expect(appRoutes.filter((route) => route.meta?.demoOnly).map(({ name }) => name)).toEqual(
      DEMO_ONLY,
    )
    expect(
      replacedAmong(withComingSoonScreens(appRoutes, BUILD_POSTURES.REAL), AWARD_SCREENS),
    ).toEqual([])
  })

  it('stay working in the client-demo build', () => {
    expect(replacedAmong(withComingSoonScreens(appRoutes, BUILD_POSTURES.DEMO), DEMO_ONLY)).toEqual(
      [],
    )
  })
})

describe('the full mock build', () => {
  it('keeps every screen working', () => {
    const mock = withComingSoonScreens(appRoutes, BUILD_POSTURES.MOCK)
    expect(replacedAmong(mock, [...AWAITING_BACKEND, ...DEMO_ONLY, ...PHASE_ONE])).toEqual([])
  })
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
