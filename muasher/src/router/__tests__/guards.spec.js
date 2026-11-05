import { beforeEach, describe, expect, it } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { setupGuards } from '@/router/guards'
import { routes } from '@/router/routes'
import { useAuthStore } from '@/stores/auth'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { ROLES } from '@/constants/roles'
import { KNOWN_PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_ACCESS } from '@/constants/routeAccess'
import { sessionUserFor } from '@/test-utils/session'

const OPEN_TO_ANY_SIGNED_IN_USER = [ROUTE_NAMES.ACCOUNT_PROFILE]

function freshRouter() {
  const router = createRouter({ history: createMemoryHistory(), routes })
  setupGuards(router)
  return router
}

async function visit(path) {
  const router = freshRouter()
  await router.push(path)
  await router.isReady()
  return router.currentRoute.value
}

function signIn(roles, extra = {}) {
  useAuthStore().setUser(sessionUserFor(roles, extra))
}

beforeEach(() => {
  setActivePinia(createPinia())
})

describe('router guards — permission-driven, deny by default', () => {
  it('bounces a guest to login with redirect=to.fullPath', async () => {
    useAuthStore().clearSession()
    const route = await visit('/assessment/self')
    expect(route.name).toBe(ROUTE_NAMES.AUTH_LOGIN)
    expect(route.query.redirect).toBe('/assessment/self')
  })

  it('lets an entity holding assessment.self.manage reach /assessment/self', async () => {
    signIn([ROLES.ENTITY])
    expect((await visit('/assessment/self')).name).toBe(ROUTE_NAMES.ASSESSMENT_SELF)
  })

  it('sends a staff user without the permission to the forbidden page', async () => {
    signIn([ROLES.ASSESSOR])
    expect((await visit('/assessment/self')).name).toBe(ROUTE_NAMES.ERRORS_FORBIDDEN)
  })

  it('has no System Administrator bypass', async () => {
    signIn([ROLES.SYSTEM_ADMIN])
    expect((await visit('/admin/settings/users')).name).toBe(ROUTE_NAMES.ADMIN_USERS)
    expect((await visit('/assessment/self')).name).toBe(ROUTE_NAMES.ERRORS_FORBIDDEN)
    expect((await visit('/admin/award/committee')).name).toBe(ROUTE_NAMES.ERRORS_FORBIDDEN)
  })

  it('follows the permission keys, not the role names', async () => {
    signIn([ROLES.SYSTEM_ADMIN], { permissions: ['dashboard.view'] })
    expect((await visit('/admin/settings/users')).name).toBe(ROUTE_NAMES.ERRORS_FORBIDDEN)
    signIn([], { permissions: ['users.view'] })
    expect((await visit('/admin/settings/users')).name).toBe(ROUTE_NAMES.ADMIN_USERS)
  })

  it('opens the committee queue only for an assessor with an active appointment', async () => {
    signIn([ROLES.ASSESSOR])
    expect((await visit('/admin/award/committee')).name).toBe(ROUTE_NAMES.ERRORS_FORBIDDEN)
    signIn([ROLES.ASSESSOR], { committeeAppointments: [{ cycleId: 'cycle-1', status: 'active' }] })
    expect((await visit('/admin/award/committee')).name).toBe(ROUTE_NAMES.AWARD_COMMITTEE)
  })

  it('declares a known permission for every protected route', () => {
    const protectedNames = routes.flatMap(function collect(route) {
      const own = route.meta?.requiresAuth && route.name ? [route.name] : []
      return [...own, ...(route.children ?? []).flatMap(collect)]
    })
    const ungated = protectedNames.filter(
      (name) => !OPEN_TO_ANY_SIGNED_IN_USER.includes(name) && !ROUTE_ACCESS[name]?.anyOf?.length,
    )
    const unknownKeys = Object.values(ROUTE_ACCESS)
      .flatMap((access) => [...access.anyOf, ...(access.allOf ?? [])])
      .filter((key) => !KNOWN_PERMISSIONS.has(key))
    expect(ungated).toEqual([])
    expect(unknownKeys).toEqual([])
  })
})

describe('router guards — self assessment and staff assessment record', () => {
  it('keeps the self form and the entity result for entity accounts only', async () => {
    signIn([ROLES.REQUEST_FOLLOWER])
    expect((await visit('/assessment/self/result/9001')).name).toBe(ROUTE_NAMES.ERRORS_FORBIDDEN)
    expect((await visit('/admin/assessments/9001')).name).toBe(ROUTE_NAMES.ADMIN_ASSESSMENT)
  })

  it('opens the result route to the entity and refuses the staff record without the permission', async () => {
    signIn([ROLES.ENTITY])
    expect((await visit('/assessment/self/result/9001')).name).toBe(
      ROUTE_NAMES.ASSESSMENT_SELF_RESULT,
    )
    signIn([ROLES.CRITERIA_OFFICER])
    expect((await visit('/admin/assessments/9001')).name).toBe(ROUTE_NAMES.ERRORS_FORBIDDEN)
  })
})

describe('router guards — payments and finance', () => {
  const PAYMENT = '3f0c9a52-6d1e-4b7a-9c21-5a1e00000001'

  it('opens the entity payment screens to entity accounts only', async () => {
    signIn([ROLES.ENTITY])
    expect((await visit('/payments')).name).toBe(ROUTE_NAMES.PAYMENTS_MINE)
    expect((await visit(`/payments/${PAYMENT}`)).name).toBe(ROUTE_NAMES.PAYMENTS_DETAIL)
    expect((await visit(`/payments/${PAYMENT}/return?id=mock_pay_1`)).name).toBe(
      ROUTE_NAMES.PAYMENTS_RETURN,
    )
    expect((await visit('/assessment/accredited/pay')).name).toBe(
      ROUTE_NAMES.ASSESSMENT_ACCREDITED_PAY,
    )
    expect((await visit('/bag/pay')).name).toBe(ROUTE_NAMES.BAG_PAY)
    signIn([], {
      permissions: ['payments.own.view', 'payments.own.create', 'assessment.accredited.manage'],
    })
    expect((await visit('/payments')).name).toBe(ROUTE_NAMES.ERRORS_FORBIDDEN)
    expect((await visit('/assessment/accredited/pay')).name).toBe(ROUTE_NAMES.ERRORS_FORBIDDEN)
  })

  it('needs the purpose permission next to payments.own.create for each checkout', async () => {
    signIn([ROLES.ENTITY], { permissions: ['payments.own.create', 'assessment.accredited.manage'] })
    expect((await visit('/assessment/accredited/pay')).name).toBe(
      ROUTE_NAMES.ASSESSMENT_ACCREDITED_PAY,
    )
    expect((await visit('/bag/pay')).name).toBe(ROUTE_NAMES.ERRORS_FORBIDDEN)
  })

  it('gates the finance screens by permission key, events for settings.payment.manage only', async () => {
    signIn([ROLES.FINANCE_OFFICER])
    expect((await visit('/finance')).name).toBe(ROUTE_NAMES.FINANCE_OVERVIEW)
    expect((await visit(`/finance/transactions/${PAYMENT}`)).name).toBe(
      ROUTE_NAMES.FINANCE_TRANSACTION,
    )
    expect((await visit('/finance/refunds')).name).toBe(ROUTE_NAMES.FINANCE_REFUNDS)
    expect((await visit('/finance/payment-events')).name).toBe(ROUTE_NAMES.ERRORS_FORBIDDEN)
    expect((await visit('/admin/settings/finance')).name).toBe(ROUTE_NAMES.ERRORS_FORBIDDEN)
    expect((await visit('/payments')).name).toBe(ROUTE_NAMES.ERRORS_FORBIDDEN)
    signIn([ROLES.SYSTEM_ADMIN])
    expect((await visit('/finance/payment-events')).name).toBe(ROUTE_NAMES.FINANCE_PAYMENT_EVENTS)
    expect((await visit('/admin/settings/finance')).name).toBe(ROUTE_NAMES.ADMIN_SETTINGS_FINANCE)
    expect((await visit('/admin/operations')).name).toBe(ROUTE_NAMES.FINANCE_OVERVIEW)
  })

  it('denies unknown permission keys on every finance route', async () => {
    signIn([], { permissions: ['finance.anything', 'payments.any'] })
    for (const path of [
      '/finance',
      '/finance/transactions',
      '/finance/refunds',
      '/finance/payment-events',
    ]) {
      expect((await visit(path)).name).toBe(ROUTE_NAMES.ERRORS_FORBIDDEN)
    }
  })
})
