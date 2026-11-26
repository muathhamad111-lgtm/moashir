import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { awardEntryGuard } from '@/router/awardEntry'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import { ROUTE_NAMES } from '@/constants/routeNames'

beforeEach(() => {
  setActivePinia(createPinia())
})

describe('/assessment/advanced entry (AJ-04)', () => {
  it('sends a signed-in entity to its award path page', () => {
    useAuthStore().setUser(sessionUserFor(['entity']))
    expect(awardEntryGuard()).toEqual({ name: ROUTE_NAMES.PATHS_AWARD, replace: true })
  })

  it('keeps the public intro for visitors and staff', () => {
    expect(awardEntryGuard()).toBe(true)
    useAuthStore().setUser(sessionUserFor(['system_admin']))
    expect(awardEntryGuard()).toBe(true)
  })
})
