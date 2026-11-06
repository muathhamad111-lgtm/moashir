import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

function localizedName(name, locale) {
  if (!name) return ''
  if (typeof name === 'string') return name
  return name[locale] || name.ar || name.en || ''
}

export function usePortalIdentity() {
  const { t, locale } = useI18n()
  const auth = useAuthStore()

  const userName = computed(
    () => localizedName(auth.user?.name, locale.value) || t('nav.userFallback'),
  )
  const firstName = computed(() => userName.value.trim().split(/\s+/)[0] ?? '')
  const entityName = computed(
    () => localizedName(auth.user?.entity?.name, locale.value) || userName.value,
  )
  const initial = computed(() => Array.from(entityName.value.trim())[0] ?? '')
  const email = computed(() => auth.user?.email ?? '')
  const roleLabel = computed(() => t('portal.account.roleLine'))

  return { userName, firstName, entityName, initial, email, roleLabel }
}
