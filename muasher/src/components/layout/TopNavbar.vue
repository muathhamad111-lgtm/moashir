<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { usePermissions } from '@/composables/usePermissions'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { accessFor } from '@/constants/routeAccess'
import { canUseGlobalSearch } from '@/constants/globalSearch'
import LocaleSwitcher from '@/components/ui/LocaleSwitcher.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import NotificationBell from '@/components/notifications/NotificationBell.vue'
import AppMenu from '@/components/ui/AppMenu.vue'
import Badge from '@/components/ui/Badge.vue'
import GlobalSearch from '@/components/search/GlobalSearch.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()
const { sidebarCollapsed } = storeToRefs(ui)
const { can, canAccess } = usePermissions()

const canAccessSettings = computed(() => canAccess(accessFor(ROUTE_NAMES.ADMIN_SETTINGS)))
const canViewEntityProfile = computed(() => can(PERMISSIONS.ENTITY_PROFILE_VIEW))
const canSearch = computed(() => canUseGlobalSearch(can))
const pageTitle = computed(() => (route.meta.titleKey ? t(route.meta.titleKey) : t('app.name')))

const displayName = computed(() => {
  const name = auth.user?.name
  if (!name) return t('nav.userFallback')
  return typeof name === 'object' ? (name[locale.value] ?? name.ar ?? name.en ?? '') : name
})

const initial = computed(() => Array.from(displayName.value.trim())[0] ?? '')

const roleLabels = computed(() => auth.roles.map((role) => t(`users.roleLabels.${role}`)))

const userActions = computed(() => [
  { key: 'account', label: t('nav.account'), icon: 'user-circle' },
  ...(canViewEntityProfile.value
    ? [{ key: 'profile', label: t('nav.entityProfile'), icon: 'user' }]
    : []),
  {
    key: 'logout',
    label: t('actions.logout'),
    icon: 'logout',
    variant: 'danger',
  },
])

const ROUTES_BY_ACTION = {
  account: ROUTE_NAMES.ACCOUNT_PROFILE,
  profile: ROUTE_NAMES.ENTITY_PROFILE,
}

async function signOut() {
  await auth.logout()
  router.push({ name: ROUTE_NAMES.AUTH_LOGIN })
}

function onUserAction(key) {
  if (key === 'logout') {
    signOut()
    return
  }
  if (ROUTES_BY_ACTION[key]) router.push({ name: ROUTES_BY_ACTION[key] })
}
</script>

<template>
  <header class="navbar navbar--app">
    <div class="navbar__start">
      <button
        type="button"
        class="btn btn--ghost btn--icon navbar__toggle"
        :aria-label="t('nav.toggleSidebar')"
        :aria-expanded="!sidebarCollapsed"
        @click="ui.toggleSidebar"
      >
        <AppIcon name="menu" />
      </button>
      <p class="navbar__title">{{ pageTitle }}</p>
    </div>
    <GlobalSearch v-if="canSearch" />
    <div class="navbar__actions">
      <LocaleSwitcher />
      <ThemeToggle />
      <router-link
        v-if="canAccessSettings"
        class="btn btn--ghost btn--icon navbar__settings"
        :to="{ name: ROUTE_NAMES.ADMIN_SETTINGS }"
        :aria-label="t('nav.adminSettings')"
        :title="t('nav.adminSettings')"
      >
        <AppIcon name="gear" />
      </router-link>
      <NotificationBell />
      <div class="navbar__user">
        <AppMenu
          :actions="userActions"
          :label="t('nav.userMenu', { name: displayName })"
          trigger="avatar"
          @select="onUserAction"
        >
          <template #trigger>
            <span aria-hidden="true">{{ initial }}</span>
          </template>
          <template #header>
            <div class="navbar__user-head">
              <span class="navbar__user-name">{{ displayName }}</span>
              <span class="navbar__user-roles">
                <Badge v-for="label in roleLabels" :key="label" variant="info">{{ label }}</Badge>
              </span>
            </div>
          </template>
        </AppMenu>
      </div>
    </div>
  </header>
</template>
