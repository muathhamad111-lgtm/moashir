import { describe, expect, it, beforeEach } from 'vitest'
import { nextTick } from 'vue'
import { createPinia } from 'pinia'
import { installLocalePreference } from '@/plugins/localePreference'
import { useAuthStore } from '@/stores/auth'
import { i18n } from '@/i18n'
import { readLocaleCookie } from '@/utils/localeCookie'
import { sessionUserFor } from '@/test-utils/session'

beforeEach(() => {
  i18n.global.locale.value = 'ar'
  document.cookie = 'locale=ar; path=/'
})

describe('installLocalePreference', () => {
  it('adopts the profile language after sign-in, over the guest cookie', async () => {
    const pinia = createPinia()
    const stop = installLocalePreference(pinia)
    useAuthStore(pinia).setUser(sessionUserFor(['criteria_officer'], { locale: 'en' }))
    await nextTick()
    expect(i18n.global.locale.value).toBe('en')
    expect(readLocaleCookie()).toBe('en')
    stop()
  })

  it('keeps the cookie language for guests and when the profile has none', async () => {
    const pinia = createPinia()
    const stop = installLocalePreference(pinia)
    useAuthStore(pinia).setUser(sessionUserFor(['criteria_officer'], { locale: null }))
    await nextTick()
    expect(i18n.global.locale.value).toBe('ar')
    stop()
  })
})
