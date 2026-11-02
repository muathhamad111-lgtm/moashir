<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { usePermissions } from '@/composables/usePermissions'
import { useUiStore } from '@/stores/ui'
import { useBackOfficeLogo } from '@/composables/useBackOfficeLogo'
import { ROUTE_NAMES } from '@/constants/routeNames'
import {
  APP_NAV_ITEMS,
  filterNavByAccess,
  groupNavItems,
  isNavItemActive,
} from '@/constants/navigation'

const { t } = useI18n()
const route = useRoute()
const { canAccess } = usePermissions()
const ui = useUiStore()
const { sidebarCollapsed } = storeToRefs(ui)
const { sidebarLogo } = useBackOfficeLogo()

const groups = computed(() => groupNavItems(filterNavByAccess(APP_NAV_ITEMS, canAccess)))

function isActive(item) {
  return isNavItemActive(item, route.name)
}
</script>

<template>
  <nav
    class="sidebar"
    :class="{ 'sidebar--collapsed': sidebarCollapsed }"
    :aria-label="t('nav.sidebarLabel')"
  >
    <router-link class="sidebar__brand" :to="{ name: ROUTE_NAMES.APP_DASHBOARD }">
      <img class="sidebar__logo" :src="sidebarLogo" :alt="t('shell.brandHome')" />
    </router-link>

    <div
      v-for="group in groups"
      :key="group.id"
      class="sidebar__group"
      role="group"
      :aria-labelledby="`sidebar-group-${group.id}`"
    >
      <p :id="`sidebar-group-${group.id}`" class="sidebar__caption">{{ t(group.labelKey) }}</p>
      <ul class="sidebar__menu">
        <li v-for="item in group.items" :key="item.id" class="sidebar__entry">
          <router-link
            class="sidebar__item"
            :class="{ 'is-active': isActive(item) }"
            :to="{ name: item.routeName }"
            :aria-current="isActive(item) ? 'page' : undefined"
            :aria-label="t(item.labelKey)"
            :title="sidebarCollapsed ? t(item.labelKey) : undefined"
          >
            <i class="sidebar__icon" :class="item.icon" aria-hidden="true"></i>
            <span class="sidebar__label">{{ t(item.labelKey) }}</span>
          </router-link>
        </li>
      </ul>
    </div>
  </nav>
</template>
