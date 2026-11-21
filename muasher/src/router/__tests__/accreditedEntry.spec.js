import { describe, expect, it, vi, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { accreditedEntryGuard, accreditedFormRedirect } from '@/router/accreditedEntry'
import { accreditedService } from '@/services/accreditedService'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import { ROUTE_NAMES } from '@/constants/routeNames'

vi.mock('@/services/accreditedService', () => ({ accreditedService: { getCurrent: vi.fn() } }))

function signIn(roles) {
  useAuthStore().setUser(sessionUserFor(roles))
}

beforeEach(() => {
  vi.clearAllMocks()
  setActivePinia(createPinia())
})

describe('/assessment/accredited redirect rule', () => {
  it('maps an editable active request to its form', () => {
    for (const status of ['draft', 'needs-completion']) {
      expect(accreditedFormRedirect({ active: { id: 14, status } })).toEqual({
        name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_FORM,
        params: { id: '14' },
      })
    }
    expect(accreditedFormRedirect({ active: null, awaitingPayment: { paymentId: 'p' } })).toBeNull()
  })

  it('keeps the introduction and its status card while the request is reviewed', () => {
    for (const status of ['submitted', 'assigned', 'under-review']) {
      expect(accreditedFormRedirect({ active: { id: 14, status } })).toBeNull()
    }
  })

  it('sends an entity with an active request to the form', async () => {
    signIn(['entity'])
    accreditedService.getCurrent.mockResolvedValue({
      data: { active: { id: 14, status: 'draft' } },
    })
    expect(await accreditedEntryGuard()).toEqual({
      name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_FORM,
      params: { id: '14' },
    })
  })

  it('shows the introduction without an active request or when the call fails', async () => {
    signIn(['entity'])
    accreditedService.getCurrent.mockResolvedValueOnce({ data: { active: null } })
    expect(await accreditedEntryGuard()).toBe(true)
    accreditedService.getCurrent.mockRejectedValueOnce(new Error('offline'))
    expect(await accreditedEntryGuard()).toBe(true)
  })

  it('never calls the API for guests and staff', async () => {
    expect(await accreditedEntryGuard()).toBe(true)
    signIn(['system_admin'])
    expect(await accreditedEntryGuard()).toBe(true)
    expect(accreditedService.getCurrent).not.toHaveBeenCalled()
  })
})
