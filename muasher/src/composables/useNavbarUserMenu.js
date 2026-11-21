import { computed, toValue } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { usePermissions } from '@/composables/usePermissions'
import { useLocale } from '@/composables/useLocale'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { accessFor } from '@/constants/routeAccess'

const ROUTES_BY_ACTION = Object.freeze({
  account: ROUTE_NAMES.ACCOUNT_PROFILE,
  profile: ROUTE_NAMES.ENTITY_PROFILE,
  settings: ROUTE_NAMES.ADMIN_SETTINGS,
})

export function useNavbarUserMenu({ holdsTools }) {
  const { t } = useI18n()
  const router = useRouter()
  const auth = useAuthStore()
  const { can, canAccess } = usePermissions()
  const { toggleLocale } = useLocale()

  const canAccessSettings = computed(() => canAccess(accessFor(ROUTE_NAMES.ADMIN_SETTINGS)))

  const toolActions = computed(() => [
    ...(canAccessSettings.value
      ? [{ key: 'settings', label: t('nav.adminSettings'), icon: 'gear' }]
      : []),
    { key: 'locale', label: t('locale.switchLabel'), icon: 'globe' },
  ])

  const actions = computed(() => [
    { key: 'account', label: t('nav.account'), icon: 'user-circle' },
    ...(can(PERMISSIONS.ENTITY_PROFILE_VIEW)
      ? [{ key: 'profile', label: t('nav.entityProfile'), icon: 'user' }]
      : []),
    ...(toValue(holdsTools) ? toolActions.value : []),
    { key: 'logout', label: t('actions.logout'), icon: 'logout', variant: 'danger' },
  ])

  async function signOut() {
    await auth.logout()
    router.push({ name: ROUTE_NAMES.AUTH_LOGIN })
  }

  const HANDLERS = { logout: signOut, locale: toggleLocale }

  function select(key) {
    if (HANDLERS[key]) return HANDLERS[key]()
    if (ROUTES_BY_ACTION[key]) router.push({ name: ROUTES_BY_ACTION[key] })
  }

  return { actions, canAccessSettings, select }
}
