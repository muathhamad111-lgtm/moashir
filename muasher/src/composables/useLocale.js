import { computed } from 'vue'
import { i18n } from '@/i18n'
import { applyLocale } from '@/i18n/activeLocale'
import { useAuthStore } from '@/stores/auth'
import { profileService } from '@/services/profileService'
import { toast } from '@/composables/useToast'
import { LOCALE_DIR, SUPPORTED_LOCALES } from '@/constants/locales'

function saveToProfile(locale) {
  const auth = useAuthStore()
  if (!auth.isAuthenticated) return Promise.resolve(false)
  auth.patchUser({ locale })
  return profileService.updatePreferences({ locale }).then(
    () => true,
    () => {
      toast.error(i18n.global.t('locale.saveFailed'))
      return false
    },
  )
}

export function useLocale() {
  const locale = computed(() => i18n.global.locale.value)
  const dir = computed(() => LOCALE_DIR[locale.value])

  function setLocale(next) {
    if (next === locale.value || !applyLocale(next)) return Promise.resolve(false)
    return saveToProfile(next)
  }

  function toggleLocale() {
    const next = SUPPORTED_LOCALES.find((candidate) => candidate !== locale.value)
    return setLocale(next)
  }

  return { locale, dir, setLocale, toggleLocale }
}
