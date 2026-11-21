import { describe, expect, it } from 'vitest'
import { RELOAD_GUARD_KEY, RELOAD_GUARD_WINDOW_MS, createReloadGuard } from '@/utils/reloadGuard'

function memoryStorage(initial = {}) {
  const values = new Map(Object.entries(initial))
  return {
    getItem: (key) => (values.has(key) ? values.get(key) : null),
    setItem: (key, value) => values.set(key, String(value)),
    values,
  }
}

function brokenStorage() {
  throw new DOMException('The operation is insecure.', 'SecurityError')
}

describe('createReloadGuard', () => {
  it('allows the first reload to a target and records it in session storage', () => {
    const storage = memoryStorage()
    const guard = createReloadGuard({ storage: () => storage, now: () => 1_000 })
    expect(guard.claim('/auth/login')).toBe(true)
    expect(JSON.parse(storage.values.get(RELOAD_GUARD_KEY))).toEqual({
      target: '/auth/login',
      at: 1_000,
    })
  })

  it('refuses a second reload to the same target inside the window, across page loads', () => {
    const storage = memoryStorage()
    let clock = 1_000
    const now = () => clock
    expect(createReloadGuard({ storage: () => storage, now }).claim('/requests')).toBe(true)
    clock += RELOAD_GUARD_WINDOW_MS - 1
    expect(createReloadGuard({ storage: () => storage, now }).claim('/requests')).toBe(false)
  })

  it('allows the same target again once the window has passed', () => {
    const storage = memoryStorage()
    let clock = 1_000
    const now = () => clock
    expect(createReloadGuard({ storage: () => storage, now }).claim('/requests')).toBe(true)
    clock += RELOAD_GUARD_WINDOW_MS
    expect(createReloadGuard({ storage: () => storage, now }).claim('/requests')).toBe(true)
  })

  it('allows a different target inside the window on the next page load', () => {
    const storage = memoryStorage()
    const now = () => 1_000
    expect(createReloadGuard({ storage: () => storage, now }).claim('/requests')).toBe(true)
    expect(createReloadGuard({ storage: () => storage, now }).claim('/dashboard')).toBe(true)
  })

  it('never reloads twice in the same page life', () => {
    const guard = createReloadGuard({ storage: () => memoryStorage(), now: () => 1_000 })
    expect(guard.claim('/requests')).toBe(true)
    expect(guard.claim('/dashboard')).toBe(false)
  })

  it('treats an unreadable record as no record', () => {
    const storage = memoryStorage({ [RELOAD_GUARD_KEY]: '{not json' })
    expect(createReloadGuard({ storage: () => storage, now: () => 1 }).claim('/requests')).toBe(
      true,
    )
  })

  it('reloads at most once per page life when session storage is unavailable', () => {
    const guard = createReloadGuard({ storage: brokenStorage, now: () => 1_000 })
    expect(guard.claim('/requests')).toBe(true)
    expect(guard.claim('/requests')).toBe(false)
  })

  it('reloads at most once per page life when writing to session storage fails', () => {
    const storage = {
      getItem: () => null,
      setItem: () => {
        throw new DOMException('Quota exceeded', 'QuotaExceededError')
      },
    }
    const guard = createReloadGuard({ storage: () => storage, now: () => 1_000 })
    expect(guard.claim('/requests')).toBe(true)
    expect(guard.claim('/dashboard')).toBe(false)
  })
})
