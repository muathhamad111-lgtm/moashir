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

describe('award routes — the links of the award notifications', () => {
  it('opens the public intro for everyone and the form for an entity only', async () => {
    useAuthStore().clearSession()
    expect(await visit('/assessment/advanced')).toBe(R.ASSESSMENT_ADVANCED)
    signIn([ROLES.ENTITY])
    expect(await visit('/assessment/advanced/form/9201')).toBe(R.ASSESSMENT_ADVANCED_FORM)
    expect(await visit('/admin/award/cycles')).toBe(R.ERRORS_FORBIDDEN)
    signIn([ROLES.REQUEST_FOLLOWER])
    expect(await visit('/assessment/advanced/form/9201')).toBe(R.ERRORS_FORBIDDEN)
  })

  it('lets the cycle managers create cycles and the readers open them', async () => {
    signIn([ROLES.SYSTEM_ADMIN])
    expect(await visit('/admin/award/cycles')).toBe(R.AWARD_CYCLES)
    expect(await visit('/admin/award/cycles/new')).toBe(R.AWARD_CYCLE_NEW)
    expect(await visit('/admin/award/cycles/31')).toBe(R.AWARD_CYCLE)
    signIn([ROLES.REQUEST_FOLLOWER])
    expect(await visit('/admin/award/cycles/31')).toBe(R.AWARD_CYCLE)
    expect(await visit('/admin/award/cycles/new')).toBe(R.ERRORS_FORBIDDEN)
  })

  it('gives the filtering screens to the filtering readers only and denies unknown permission keys', async () => {
    signIn([ROLES.FILTERING_OFFICER])
    expect(await visit('/admin/award/filtering')).toBe(R.AWARD_FILTERING)
    expect(await visit('/admin/award/cycles/31/filtering')).toBe(R.AWARD_FILTERING_QUEUE)
    expect(await visit('/admin/award/cycles/31/filtering/9205')).toBe(R.AWARD_FILTERING_DETAIL)
    signIn([ROLES.ASSESSOR])
    expect(await visit('/admin/award/cycles/31/filtering')).toBe(R.ERRORS_FORBIDDEN)
    signIn([], { permissions: ['award.filtering.view.all'] })
    expect(await visit('/admin/award/filtering')).toBe(R.ERRORS_FORBIDDEN)
  })

  it('keeps the committee screens under the committee naming', async () => {
    signIn([ROLES.AWARD_SECRETARY])
    expect(await visit('/admin/award/committee/manage')).toBe(R.AWARD_COMMITTEE_MANAGEMENT)
    expect(await visit('/admin/award/secretary')).toBe(R.AWARD_SECRETARY)
  })

  it('opens the roster, desk and results to their readers and the committee work to seated assessors only', async () => {
    signIn([ROLES.AWARD_SECRETARY])
    expect(await visit('/admin/award/cycles/31/committee')).toBe(R.AWARD_COMMITTEE_ROSTER)
    expect(await visit('/admin/award/cycles/31/secretary')).toBe(R.AWARD_SECRETARY_DESK)
    expect(await visit('/admin/award/cycles/31/secretary/9205')).toBe(R.AWARD_SECRETARY_REVIEW)
    expect(await visit('/admin/award/cycles/31/results')).toBe(R.AWARD_RESULTS_BOARD)
    expect(await visit('/admin/award/cycles/31/results/national')).toBe(R.AWARD_NATIONAL)
    expect(await visit('/admin/committee/assignments')).toBe(R.ERRORS_FORBIDDEN)
    signIn([ROLES.REQUEST_FOLLOWER])
    expect(await visit('/admin/award/cycles/31/committee')).toBe(R.AWARD_COMMITTEE_ROSTER)
    expect(await visit('/admin/award/cycles/31/secretary')).toBe(R.ERRORS_FORBIDDEN)
    expect(await visit('/admin/award/cycles/31/results')).toBe(R.ERRORS_FORBIDDEN)
  })
})
