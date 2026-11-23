import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import RequestHeaderActions from '../RequestHeaderActions.vue'
import AssignedRequestActions from '../AssignedRequestActions.vue'

// Live bug 2026-11-22: the request page's «Start the assessment» did nothing while the assigned list's worked.
// The two must open the same review for every path and role.
const ASSESSOR_ID = 7001

function mountHeader(detail, { roles, userId }) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles, { id: userId }))
  const wrapper = mount(RequestHeaderActions, { props: { detail }, global: globals })
  return { wrapper, router: globals.router }
}

function mountListActions(detail) {
  const globals = createTestGlobals({ locale: 'en' })
  return mount(AssignedRequestActions, {
    props: { requestId: detail.id, requestNumber: 'REQ', status: detail.status },
    global: globals,
  })
}

function assigned(id, path, status, assessorId = ASSESSOR_ID) {
  return {
    id,
    path,
    status,
    assignmentHistory: [{ id: 1, status: 'active', assessor: { id: assessorId } }],
  }
}

const CASES = [
  {
    name: 'the assessor starts an accredited assessment',
    detail: assigned(9107, 'accredited', 'assigned'),
    viewer: { roles: ['assessor'], userId: ASSESSOR_ID },
    label: 'Start the assessment',
  },
  {
    name: 'the assessor starts an advanced initial assessment',
    detail: assigned(9302, 'advanced', 'assigned'),
    viewer: { roles: ['assessor'], userId: ASSESSOR_ID },
    label: 'Start the assessment',
  },
  {
    name: 'the assessor continues an advanced initial assessment',
    detail: assigned(9303, 'advanced', 'under-initial-assessment'),
    viewer: { roles: ['assessor'], userId: ASSESSOR_ID },
    label: 'Continue the assessment',
  },
  {
    name: 'a request follower opens the review of a request assigned to someone else',
    detail: assigned(9108, 'accredited', 'under-review', 10),
    viewer: { roles: ['request_follower'], userId: 20 },
    label: 'Open the review',
  },
]

describe('opening the review from the request page', () => {
  it.each(CASES)('$name', async ({ detail, viewer, label }) => {
    const { wrapper, router } = mountHeader(detail, viewer)
    await router.isReady()
    const button = wrapper.get('[data-testid="request-open-review"]')
    expect(button.text()).toBe(label)
    await button.trigger('click')
    // The review page loads lazily, which takes longer on a busy machine.
    await vi.waitFor(
      () => expect(router.currentRoute.value.path).toBe(`/admin/requests/${detail.id}/review`),
      { timeout: 5000 },
    )
    expect(router.currentRoute.value.query).toEqual({})
    wrapper.unmount()
  })

  it.each(CASES)('goes where the assigned list goes: $name', ({ detail, viewer }) => {
    const { wrapper } = mountHeader(detail, viewer)
    const headerHref = wrapper.get('[data-testid="request-open-review"]').attributes('href')
    const listLinks = mountListActions(detail).findAll('a')
    expect(headerHref).toBe(listLinks.at(-1).attributes('href'))
    wrapper.unmount()
  })
})
