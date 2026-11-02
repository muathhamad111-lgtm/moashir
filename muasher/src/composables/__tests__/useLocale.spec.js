import { describe, expect, it, vi, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useLocale } from '@/composables/useLocale'
import { useAuthStore } from '@/stores/auth'
import { profileService } from '@/services/profileService'
import { toast } from '@/composables/useToast'
import { i18n } from '@/i18n'
import { readLocaleCookie } from '@/utils/localeCookie'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/profileService', () => ({ profileService: { updatePreferences: vi.fn() } }))
vi.mock('@/composables/useToast', () => ({
  toast: { error: vi.fn(), success: vi.fn(), info: vi.fn() },
}))

beforeEach(() => {
  vi.clearAllMocks()
  setActivePinia(createPinia())
  i18n.global.locale.value = 'ar'
  document.cookie = 'locale=ar; path=/'
  profileService.updatePreferences.mockResolvedValue({ data: { locale: 'en', theme: 'system' } })
})

describe('useLocale — locale persistence', () => {
  it('keeps a guest choice in the locale cookie without calling the profile', async () => {
    await useLocale().setLocale('en')
    expect(i18n.global.locale.value).toBe('en')
    expect(readLocaleCookie()).toBe('en')
    expect(profileService.updatePreferences).not.toHaveBeenCalled()
  })

  it('saves a signed-in choice to the profile and the session user', async () => {
    const auth = useAuthStore()
    auth.setUser(sessionUserFor(['criteria_officer'], { locale: 'ar' }))
    await useLocale().toggleLocale()
    expect(profileService.updatePreferences).toHaveBeenCalledWith({ locale: 'en' })
    expect(auth.user.locale).toBe('en')
    expect(readLocaleCookie()).toBe('en')
  })

  it('keeps the switched language and warns when the profile cannot be saved', async () => {
    useAuthStore().setUser(sessionUserFor(['criteria_officer'], { locale: 'ar' }))
    profileService.updatePreferences.mockRejectedValueOnce(new Error('offline'))
    expect(await useLocale().setLocale('en')).toBe(false)
    expect(i18n.global.locale.value).toBe('en')
    expect(toast.error).toHaveBeenCalled()
  })

  it('ignores unsupported or unchanged locales', async () => {
    expect(await useLocale().setLocale('fr')).toBe(false)
    expect(await useLocale().setLocale('ar')).toBe(false)
    expect(profileService.updatePreferences).not.toHaveBeenCalled()
  })
})
