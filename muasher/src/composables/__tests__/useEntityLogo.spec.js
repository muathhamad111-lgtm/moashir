import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { useEntityLogo } from '@/composables/useEntityLogo'
import { useAuthStore } from '@/stores/auth'
import { useConfirm } from '@/composables/useConfirm'
import { entitiesService } from '@/services/entitiesService'
import { authService } from '@/services/authService'
import { withSetup } from '@/test-utils/withSetup'

vi.mock('@/services/entitiesService', () => ({
  entitiesService: { uploadLogo: vi.fn(), removeLogo: vi.fn() },
}))
vi.mock('@/services/authService', () => ({
  UNAUTHENTICATED_STATUS: 401,
  authService: { session: vi.fn() },
}))
vi.mock('@/utils/imageDimensions', () => ({
  imageDimensionsOf: vi.fn().mockResolvedValue({ width: 400, height: 400 }),
}))

const LOGO_AVATAR = { source: 'entity_logo', url: '/api/v1/me/avatar?v=logo' }

beforeEach(() => vi.clearAllMocks())

describe('useEntityLogo — account avatar', () => {
  it('refreshes the navbar avatar after the logo is uploaded and after it is removed', async () => {
    const { api, globals } = withSetup(useEntityLogo)
    const auth = useAuthStore(globals.pinia)
    auth.setUser({ id: 1, roles: ['entity'], entityId: 5001, avatar: null })
    entitiesService.uploadLogo.mockResolvedValue({ data: { logo: { id: 'logo' } } })
    authService.session.mockResolvedValueOnce({ data: { avatar: LOGO_AVATAR } })

    await api.upload(new File([new Uint8Array(10)], 'logo.png', { type: 'image/png' }))
    await flushPromises()
    expect(auth.user.avatar).toEqual(LOGO_AVATAR)

    entitiesService.removeLogo.mockResolvedValue({ data: { logo: null } })
    authService.session.mockResolvedValueOnce({ data: { avatar: null } })
    const removal = api.remove()
    useConfirm().accept()
    await removal
    await flushPromises()
    expect(auth.user.avatar).toBeNull()
    expect(authService.session).toHaveBeenCalledTimes(2)
  })
})
