import { watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { applyLocale } from '@/i18n/activeLocale'

export function installLocalePreference(pinia) {
  const auth = useAuthStore(pinia)
  return watch(
    () => auth.user?.locale ?? null,
    (profileLocale) => {
      if (profileLocale) applyLocale(profileLocale)
    },
    { immediate: true },
  )
}
