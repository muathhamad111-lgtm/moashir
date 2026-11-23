import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { useEntityLogo } from '@/composables/useEntityLogo'
import { useAuthStore } from '@/stores/auth'
import { useEntityStore } from '@/stores/entity'
import { useConfirm } from '@/composables/useConfirm'
import { entitiesService } from '@/services/entitiesService'
import { authService } from '@/services/authService'
import { profileChangeRequestsService } from '@/services/profileChangeRequestsService'
import { withSetup } from '@/test-utils/withSetup'

vi.mock('@/services/entitiesService', () => ({
  entitiesService: { uploadLogo: vi.fn(), removeLogo: vi.fn() },
}))
vi.mock('@/services/authService', () => ({
  UNAUTHENTICATED_STATUS: 401,
  authService: { session: vi.fn() },
}))
vi.mock('@/services/profileChangeRequestsService', () => ({
  profileChangeRequestsService: { ownPending: vi.fn() },
}))
vi.mock('@/utils/imageDimensions', () => ({
  imageDimensionsOf: vi.fn().mockResolvedValue({ width: 400, height: 400 }),
}))

const PENDING_LOGO = {
  item: 'logo',
  kind: 'file',
  oldValue: null,
  newValue: { id: 'f-1', name: 'logo.png', url: '/files/f-1' },
  removes: false,
}

beforeEach(() => vi.clearAllMocks())

describe('useEntityLogo — approval', () => {
  it('sends an uploaded logo for approval without changing the account avatar', async () => {
    const { api, globals } = withSetup(useEntityLogo)
    const auth = useAuthStore(globals.pinia)
    auth.setUser({ id: 1, roles: ['entity'], entityId: 5001, avatar: null })
    entitiesService.uploadLogo.mockResolvedValue({
      data: { logo: null, pendingProfileChange: { id: 'r-1', items: ['logo'] } },
    })
    profileChangeRequestsService.ownPending.mockResolvedValue({
      data: { id: 'r-1', changes: [PENDING_LOGO] },
    })

    await api.upload(new File([new Uint8Array(10)], 'logo.png', { type: 'image/png' }))
    await flushPromises()

    expect(useEntityStore(globals.pinia).pendingChange.changes).toEqual([PENDING_LOGO])
    expect(api.pendingChange.value).toEqual(PENDING_LOGO)
    expect(api.logo.value).toBeNull()
    expect(authService.session).not.toHaveBeenCalled()
    expect(auth.user.avatar).toBeNull()
  })

  it('sends a logo removal for approval after the confirmation', async () => {
    const { api, globals } = withSetup(useEntityLogo)
    useAuthStore(globals.pinia).setUser({ id: 1, roles: ['entity'], entityId: 5001 })
    entitiesService.removeLogo.mockResolvedValue({
      data: { logo: { id: 'logo' }, pendingProfileChange: { id: 'r-1', items: ['logo'] } },
    })
    profileChangeRequestsService.ownPending.mockResolvedValue({
      data: { id: 'r-1', changes: [{ ...PENDING_LOGO, newValue: null, removes: true }] },
    })

    const removal = api.remove()
    useConfirm().accept()
    await removal
    await flushPromises()

    expect(entitiesService.removeLogo).toHaveBeenCalledTimes(1)
    expect(api.pendingChange.value.removes).toBe(true)
    expect(api.logo.value).toEqual({ id: 'logo' })
  })
})
