import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { appMenuEntryGuard } from '@/router/appMenuEntry'
import { useAuthStore } from '@/stores/auth'
import { ROLES } from '@/constants/roles'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { sessionUserFor } from '@/test-utils/session'

beforeEach(() => setActivePinia(createPinia()))

describe('appMenuEntryGuard', () => {
  it('sends an entity-only account to its own «المزيد»', () => {
    useAuthStore().setUser(sessionUserFor([ROLES.ENTITY]))
    expect(appMenuEntryGuard()).toEqual({ name: ROUTE_NAMES.PORTAL_MORE })
  })

  it('lets a back-office account in', () => {
    useAuthStore().setUser(sessionUserFor([ROLES.ASSESSOR]))
    expect(appMenuEntryGuard()).toBe(true)
  })
})
