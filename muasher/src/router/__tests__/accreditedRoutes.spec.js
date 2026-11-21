import { beforeEach, describe, expect, it } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { setupGuards } from '@/router/guards'
import { routes } from '@/router/routes'
import { useAuthStore } from '@/stores/auth'
import { ROUTE_NAMES as R } from '@/constants/routeNames'
import { ROLES } from '@/constants/roles'
import { sessionUserFor } from '@/test-utils/session'

async function open(path) {
  const router = createRouter({ history: createMemoryHistory(), routes })
  setupGuards(router)
  await router.push(path)
  await router.isReady()
  return router.currentRoute.value
}

async function visit(path) {
  return (await open(path)).name
}

function signIn(roles, extra = {}) {
  useAuthStore().setUser(sessionUserFor(roles, extra))
}

beforeEach(() => {
  setActivePinia(createPinia())
})

describe('accredited and request routes — the links of the notifications', () => {
  it('opens the entity screens for an entity account only', async () => {
    signIn([ROLES.ENTITY])
    expect(await visit('/assessment/accredited')).toBe(R.ASSESSMENT_ACCREDITED)
    expect(await visit('/assessment/accredited/form/9101')).toBe(R.ASSESSMENT_ACCREDITED_FORM)
    expect(await visit('/assessment/accredited/result/9102')).toBe(R.PATHS_ACCREDITED_REQUEST)
    expect(await visit('/paths/accredited')).toBe(R.PATHS_ACCREDITED)
    expect(await visit('/admin/requests/9108')).toBe(R.ERRORS_FORBIDDEN)
    signIn([ROLES.REQUEST_FOLLOWER])
    expect(await visit('/assessment/accredited/form/9101')).toBe(R.ERRORS_FORBIDDEN)
  })

  it('gives the follower the queue, the detail, the read-only review and the bundle page', async () => {
    signIn([ROLES.REQUEST_FOLLOWER])
    expect(await visit('/admin/requests')).toBe(R.REQUESTS_INDEX)
    expect(await visit('/admin/requests/9108')).toBe(R.REQUESTS_DETAIL)
    expect(await visit('/admin/requests/9108/review')).toBe(R.REQUESTS_REVIEW)
    expect(
      await visit('/admin/requests/9108/evidence-bundles/7c2e5a10-9108-4b6d-9e8f-000000000001'),
    ).toBe(R.REQUESTS_EVIDENCE_BUNDLE)
    expect(await visit('/admin/requests/assigned')).toBe(R.ERRORS_FORBIDDEN)
  })

  it('gives the assessor the assigned list, the detail and the review but not the queue', async () => {
    signIn([ROLES.ASSESSOR])
    expect(await visit('/admin/requests/assigned')).toBe(R.REQUESTS_ASSIGNED)
    expect(await visit('/admin/requests/9108/review')).toBe(R.REQUESTS_REVIEW)
    expect(await visit('/admin/requests/9108')).toBe(R.REQUESTS_DETAIL)
    expect(await visit('/admin/requests')).toBe(R.ERRORS_FORBIDDEN)
  })

  it('denies unknown permission keys and staff without a request permission', async () => {
    signIn([ROLES.CONTENT_OFFICER], { permissions: ['requests.everything'] })
    expect(await visit('/admin/requests')).toBe(R.ERRORS_FORBIDDEN)
    expect(await visit('/admin/requests/9108/review')).toBe(R.ERRORS_FORBIDDEN)
    signIn([ROLES.FINANCE_OFFICER])
    expect(await visit('/admin/settings/assessment')).toBe(R.ERRORS_FORBIDDEN)
  })
})

describe('path routes (03 §19.2, Q-197 / Q-198)', () => {
  it('opens the three path pages and the request detail for an entity', async () => {
    signIn([ROLES.ENTITY])
    expect(await visit('/paths/self')).toBe(R.PATHS_SELF)
    expect(await visit('/paths/accredited')).toBe(R.PATHS_ACCREDITED)
    expect(await visit('/paths/award')).toBe(R.PATHS_AWARD)
    const detail = await open('/paths/accredited/9102?tab=certificate')
    expect(detail.name).toBe(R.PATHS_ACCREDITED_REQUEST)
    expect(detail.query.tab).toBe('certificate')
  })

  it('redirects the old result, feedback and history links', async () => {
    signIn([ROLES.ENTITY])
    const result = await open('/assessment/accredited/result/9102')
    expect([result.name, result.params.id, result.query.tab]).toEqual([
      R.PATHS_ACCREDITED_REQUEST,
      '9102',
      'result',
    ])
    const feedback = await open('/assessment/accredited/result/9102/feedback')
    expect([feedback.name, feedback.query.tab]).toEqual([R.PATHS_ACCREDITED_REQUEST, 'feedback'])
    expect(await visit('/assessment/history')).toBe(R.APP_DASHBOARD)
  })

  it('keeps the path pages away from staff accounts', async () => {
    signIn([ROLES.REQUEST_FOLLOWER])
    expect(await visit('/paths/accredited/9102')).toBe(R.ERRORS_FORBIDDEN)
  })
})
