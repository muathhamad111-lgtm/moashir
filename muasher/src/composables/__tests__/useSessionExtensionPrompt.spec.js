import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { useSessionExtensionPrompt } from '@/composables/useSessionExtensionPrompt'
import { useAuthStore } from '@/stores/auth'
import { authService } from '@/services/authService'
import { markServerActivity } from '@/api/sessionEvents'
import { toast } from '@/composables/useToast'
import { withSetup } from '@/test-utils/withSetup'

vi.mock('@/services/authService', () => ({
  UNAUTHENTICATED_STATUS: 401,
  authService: { session: vi.fn(), logout: vi.fn() },
}))

const MINUTE = 60 * 1000
const ENTITY = { id: 7, roles: ['entity'], idleTimeoutMinutes: 60 }

function mountPrompt(user = ENTITY) {
  const saveBeforeSignOut = vi.fn().mockResolvedValue()
  const setup = withSetup(() => useSessionExtensionPrompt({ saveBeforeSignOut }))
  useAuthStore().setUser(user)
  return { ...setup, saveBeforeSignOut }
}

async function advance(ms) {
  await vi.advanceTimersByTimeAsync(ms)
  await flushPromises()
}

beforeEach(() => {
  vi.useFakeTimers()
  vi.clearAllMocks()
  vi.spyOn(toast, 'info')
  markServerActivity()
  authService.session.mockResolvedValue({ data: ENTITY })
  authService.logout.mockResolvedValue({})
})

afterEach(() => vi.useRealTimers())

describe('useSessionExtensionPrompt', () => {
  it('asks 15 minutes before the idle limit and extends the session on request', async () => {
    const { api } = mountPrompt()
    await advance(44 * MINUTE)
    expect(api.open.value).toBe(false)
    await advance(1 * MINUTE)
    expect(api.open.value).toBe(true)
    expect(api.countdown.value).toBe('15:00')

    await api.extend()
    expect(authService.session).toHaveBeenCalledTimes(1)
    expect(api.open.value).toBe(false)
  })

  it('asks every two hours while the user keeps working', async () => {
    const { api } = mountPrompt()
    for (let round = 0; round < 11; round += 1) {
      markServerActivity()
      await advance(10 * MINUTE)
    }
    markServerActivity()
    await advance(9 * MINUTE)
    expect(api.open.value).toBe(false)
    await advance(2 * MINUTE)
    expect(api.open.value).toBe(true)
  })

  it('saves the draft and signs out when the 15 minutes pass without an extension', async () => {
    const { api, saveBeforeSignOut, globals } = mountPrompt({ ...ENTITY, idleTimeoutMinutes: null })
    await globals.router.push('/assessment/self')
    const push = vi.spyOn(globals.router, 'push').mockResolvedValue()
    await advance(120 * MINUTE)
    expect(api.open.value).toBe(true)
    await advance(15 * MINUTE)

    expect(saveBeforeSignOut).toHaveBeenCalledTimes(1)
    expect(authService.logout).toHaveBeenCalledTimes(1)
    expect(api.open.value).toBe(false)
    expect(toast.info).toHaveBeenCalled()
    expect(push).toHaveBeenCalledWith({
      name: 'auth.login',
      query: { redirect: '/assessment/self' },
    })
  })
})
