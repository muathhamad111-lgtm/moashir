<script setup>
import { computed, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { usePermissions } from '@/composables/usePermissions'
import { useElementWidth } from '@/composables/useElementWidth'
import { useCompactViewport } from '@/composables/useCompactViewport'
import { useNavbarUserMenu } from '@/composables/useNavbarUserMenu'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { NAVBAR_TOOLS_INLINE_MIN_WIDTH } from '@/constants/navigation'
import { SEARCH_INLINE_MIN_NAVBAR_WIDTH, canUseGlobalSearch } from '@/constants/globalSearch'
import LocaleSwitcher from '@/components/ui/LocaleSwitcher.vue'
import NotificationBell from '@/components/notifications/NotificationBell.vue'
import AppMenu from '@/components/ui/AppMenu.vue'
import Badge from '@/components/ui/Badge.vue'
import GlobalSearch from '@/components/search/GlobalSearch.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AccountAvatar from '@/components/account/AccountAvatar.vue'
import AppTopBarPhone from './AppTopBarPhone.vue'

const { t, locale } = useI18n()
const auth = useAuthStore()
const ui = useUiStore()
const { sidebarCollapsed } = storeToRefs(ui)
const { can } = usePermissions()
// Phones (below md) have no sidebar: the bar starts with the logo, or back and the screen title.
const isPhone = useCompactViewport()

const canSearch = computed(() => canUseGlobalSearch(can))
const navbarWidth = useElementWidth(useTemplateRef('navbar'), Number.POSITIVE_INFINITY)
const isSearchCompact = computed(() => navbarWidth.value < SEARCH_INLINE_MIN_NAVBAR_WIDTH)
const menuHoldsTools = computed(() => navbarWidth.value < NAVBAR_TOOLS_INLINE_MIN_WIDTH)
const {
  actions: userActions,
  canAccessSettings,
  select: onUserAction,
} = useNavbarUserMenu({ holdsTools: menuHoldsTools })

const displayName = computed(() => {
  const name = auth.user?.name
  if (!name) return t('nav.userFallback')
  return typeof name === 'object' ? (name[locale.value] ?? name.ar ?? name.en ?? '') : name
})

const roleLabels = computed(() => auth.roles.map((role) => t(`users.roleLabels.${role}`)))
</script>

<template>
  <header ref="navbar" class="navbar navbar--app">
    <div class="navbar__start">
      <AppTopBarPhone v-if="isPhone" />
      <button
        v-else
        type="button"
        class="btn btn--ghost btn--icon navbar__toggle"
        :aria-label="t('nav.toggleSidebar')"
        :aria-expanded="!sidebarCollapsed"
        @click="ui.toggleSidebar"
      >
        <AppIcon name="menu" />
      </button>
    </div>
    <GlobalSearch v-if="canSearch" :compact="isSearchCompact" />
    <div class="navbar__actions">
      <template v-if="!menuHoldsTools">
        <LocaleSwitcher class="navbar__locale" />
        <router-link
          v-if="canAccessSettings"
          class="btn btn--ghost btn--icon navbar__settings"
          :to="{ name: ROUTE_NAMES.ADMIN_SETTINGS }"
          :aria-label="t('nav.adminSettings')"
          :title="t('nav.adminSettings')"
        >
          <AppIcon name="gear" />
        </router-link>
      </template>
      <NotificationBell />
      <div class="navbar__user">
        <AppMenu
          :actions="userActions"
          :label="t('nav.userMenu', { name: displayName })"
          trigger="avatar"
          @select="onUserAction"
        >
          <template #trigger>
            <AccountAvatar />
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
