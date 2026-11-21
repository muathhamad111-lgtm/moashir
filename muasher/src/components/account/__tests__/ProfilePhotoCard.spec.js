import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ProfilePhotoCard from '@/components/account/ProfilePhotoCard.vue'
import { accountAvatarService } from '@/services/accountAvatarService'
import { imageDimensionsOf } from '@/utils/imageDimensions'
import { useAuthStore } from '@/stores/auth'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/accountAvatarService', () => ({
  accountAvatarService: { upload: vi.fn(), remove: vi.fn() },
}))
vi.mock('@/utils/imageDimensions', () => ({ imageDimensionsOf: vi.fn() }))

const PHOTO = { source: 'photo', url: '/api/v1/me/avatar?v=1' }

function mountCard(extra = {}, locale = 'ar') {
  const globals = createTestGlobals({ locale })
  const auth = useAuthStore(globals.pinia)
  auth.setUser(sessionUserFor(['assessor'], { name: 'Sara', ...extra }))
  return { wrapper: mount(ProfilePhotoCard, { global: globals }), auth }
}

async function choose(wrapper, file) {
  const input = wrapper.find('[data-testid="profile-photo-input"]')
  Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
  await input.trigger('change')
  await flushPromises()
}

function png(size = 1024) {
  return new File([new Uint8Array(size)], 'photo.png', { type: 'image/png' })
}

beforeEach(() => {
  vi.clearAllMocks()
  imageDimensionsOf.mockResolvedValue({ width: 400, height: 400 })
  useToast().toasts.forEach((item) => useToast().dismiss(item.id))
})

describe('ProfilePhotoCard', () => {
  it('offers an upload with the rules and no remove action without a photo', () => {
    const { wrapper } = mountCard({}, 'en')
    expect(wrapper.text()).toContain('Profile photo')
    expect(wrapper.text()).toContain('between 200 and 2000 pixels')
    expect(wrapper.text()).toContain('Upload photo')
    expect(wrapper.find('[data-testid="profile-photo-remove"]').exists()).toBe(false)
    expect(wrapper.find('[data-icon="user"]').exists()).toBe(true)
  })

  it('uploads a valid photo, updates the session avatar and confirms with a toast', async () => {
    accountAvatarService.upload.mockResolvedValue({ data: { avatar: PHOTO } })
    const { wrapper, auth } = mountCard()
    const file = png()
    await choose(wrapper, file)
    expect(accountAvatarService.upload).toHaveBeenCalledWith(file)
    expect(auth.user.avatar).toEqual(PHOTO)
    expect(wrapper.find('img').attributes('src')).toBe(PHOTO.url)
    expect(wrapper.text()).toContain('تغيير الصورة')
    expect(useToast().toasts.at(-1).message).toBe('حُفظت الصورة الشخصية.')
  })

  it('refuses a wrong type, an oversized file and wrong dimensions before uploading', async () => {
    const { wrapper } = mountCard()
    await choose(wrapper, new File(['gif'], 'photo.gif', { type: 'image/gif' }))
    expect(wrapper.find('[role="alert"]').text()).toBe('يجب أن تكون الصورة بصيغة PNG أو JPEG.')
    await choose(wrapper, png(2 * 1024 * 1024 + 1))
    expect(wrapper.find('[role="alert"]').text()).toBe('يجب ألا يتجاوز حجم الصورة 2 ميجابايت.')
    imageDimensionsOf.mockResolvedValue({ width: 120, height: 120 })
    await choose(wrapper, png())
    expect(wrapper.find('[role="alert"]').text()).toContain('بين 200 و2000')
    expect(wrapper.find('[data-testid="profile-photo-input"]').attributes('aria-describedby')).toBe(
      'profile-photo-error',
    )
    expect(accountAvatarService.upload).not.toHaveBeenCalled()
  })

  it('shows the server validation message when the upload is refused', async () => {
    accountAvatarService.upload.mockRejectedValue({
      normalized: { fieldErrors: { file: ['The file content does not match its type.'] } },
    })
    const { wrapper, auth } = mountCard()
    await choose(wrapper, png())
    expect(wrapper.find('[role="alert"]').text()).toBe('The file content does not match its type.')
    expect(auth.user.avatar ?? null).toBeNull()
  })

  it('removes the photo after confirmation and keeps it when the dialog is dismissed', async () => {
    accountAvatarService.remove.mockResolvedValue({ data: { avatar: null } })
    const { wrapper, auth } = mountCard({ avatar: PHOTO })
    const { accept, dismiss, confirmState } = useConfirm()

    await wrapper.find('[data-testid="profile-photo-remove"]').trigger('click')
    expect(confirmState.isOpen).toBe(true)
    dismiss()
    await flushPromises()
    expect(accountAvatarService.remove).not.toHaveBeenCalled()

    await wrapper.find('[data-testid="profile-photo-remove"]').trigger('click')
    accept()
    await flushPromises()
    expect(accountAvatarService.remove).toHaveBeenCalled()
    expect(auth.user.avatar).toBeNull()
    expect(wrapper.find('img').exists()).toBe(false)
  })
})
