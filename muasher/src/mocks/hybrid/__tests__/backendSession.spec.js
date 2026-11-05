import { describe, expect, it, vi } from 'vitest'
import {
  createBackendSession,
  demoDescriptorOf,
  isAuthTransition,
  SESSION_CACHE_MS,
} from '@/mocks/hybrid/backendSession'

function sessionResponse(data, status = 200) {
  return new Response(
    JSON.stringify(
      status === 200 ? { data, meta: {}, errors: null } : { message: 'Unauthenticated.' },
    ),
    {
      status,
      headers: { 'Content-Type': 'application/json' },
    },
  )
}

const ENTITY_USER = {
  id: 41,
  name: 'جمعية الأمل الخيرية',
  locale: 'ar',
  roles: [{ key: 'entity', label: {} }],
  is_super_admin: false,
  committee_appointments: [],
}

describe('backend session bridge', () => {
  it('maps a signed-in entity to the demo entity so demo modules have data', () => {
    expect(demoDescriptorOf(ENTITY_USER)).toEqual({
      id: 5001,
      name: 'جمعية الأمل الخيرية',
      roles: ['entity'],
      entityId: 5001,
      committeeCycles: [],
      locale: 'ar',
      superAdmin: false,
    })
  })

  it('keeps the real roles of staff, the super admin flag and committee appointments', () => {
    const staff = demoDescriptorOf({
      id: 7,
      name: 'Sara',
      locale: 'en',
      roles: [{ key: 'system_admin' }],
      is_super_admin: false,
    })
    expect(staff.roles).toEqual(['system_admin'])
    expect(staff.entityId).toBeNull()
    const superAdmin = demoDescriptorOf({ id: 1, name: 'Owner', roles: [], is_super_admin: true })
    expect(superAdmin.superAdmin).toBe(true)
    const appointed = demoDescriptorOf({
      id: 9,
      name: 'Reem',
      roles: [{ key: 'assessor' }],
      committee_appointments: [{ cycle_id: 31 }],
    })
    expect(appointed.committeeCycles.length).toBe(1)
    expect(demoDescriptorOf(null)).toBeNull()
  })

  it('reads the real session once within the cache window and again after invalidation', async () => {
    let clock = 1000
    const fetcher = vi.fn(async () => sessionResponse(ENTITY_USER))
    const session = createBackendSession({ fetcher, now: () => clock })
    await Promise.all([session.refresh(), session.refresh()])
    await session.refresh()
    expect(fetcher).toHaveBeenCalledTimes(1)
    expect(session.descriptor().entityId).toBe(5001)

    clock += SESSION_CACHE_MS
    await session.refresh()
    expect(fetcher).toHaveBeenCalledTimes(2)

    session.invalidate()
    await session.refresh()
    expect(fetcher).toHaveBeenCalledTimes(3)
  })

  it('asks the real backend for JSON and bypasses the mock layer', async () => {
    const fetcher = vi.fn(async () => sessionResponse(ENTITY_USER))
    await createBackendSession({ fetcher }).refresh()
    const request = fetcher.mock.calls[0][0]
    expect(new URL(request.url).pathname).toBe('/api/v1/auth/session')
    expect(request.headers.get('accept')).toContain('application/json')
    expect(request.headers.get('accept')).toContain('msw/passthrough')
  })

  it('treats a guest or a failed read as no session', async () => {
    const guest = createBackendSession({ fetcher: async () => sessionResponse(null, 401) })
    await guest.refresh()
    expect(guest.descriptor()).toBeNull()
    const broken = createBackendSession({
      fetcher: async () => {
        throw new TypeError('Failed to fetch')
      },
    })
    await broken.refresh()
    expect(broken.descriptor()).toBeNull()
  })

  it('recognises the sign-in and sign-out calls that change the session', () => {
    const post = (path) => new Request(`http://localhost${path}`, { method: 'POST' })
    expect(isAuthTransition(post('/api/v1/auth/login'))).toBe(true)
    expect(isAuthTransition(post('/api/v1/auth/logout'))).toBe(true)
    expect(isAuthTransition(post('/api/v1/auth/mfa/confirm'))).toBe(true)
    expect(isAuthTransition(post('/api/v1/auth/otp/request'))).toBe(false)
    expect(isAuthTransition(new Request('http://localhost/api/v1/auth/session'))).toBe(false)
  })
})
