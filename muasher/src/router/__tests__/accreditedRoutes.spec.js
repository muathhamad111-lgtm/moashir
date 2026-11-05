import { beforeEach, describe, expect, it } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { setupGuards } from '@/router/guards'
import { routes } from '@/router/routes'
import { useAuthStore } from '@/stores/auth'
import { ROUTE_NAMES as R } from '@/constants/routeNames'
import { ROLES } from '@/constants/roles'
import { sessionUserFor } from '@/test-utils/session'

async function visit(path) {
  const router = createRouter({ history: createMemoryHistory(), routes })
  setupGuards(router)
  await router.push(path)
  await router.isReady()
  return router.currentRoute.value.name
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
    expect(await visit('/assessment/accredited/result/9102')).toBe(R.ASSESSMENT_ACCREDITED_RESULT)
    expect(await visit('/assessment/accredited/result/9102/feedback')).toBe(
      R.ASSESSMENT_ACCREDITED_FEEDBACK,
    )
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
