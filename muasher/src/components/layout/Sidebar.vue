<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { usePermissions } from '@/composables/usePermissions'
import { useRequestPathNav } from '@/composables/useRequestPathNav'
import { useSidebarGroups } from '@/composables/useSidebarGroups'
import { useUiStore } from '@/stores/ui'
import { useBackOfficeLogo } from '@/composables/useBackOfficeLogo'
import { ROUTE_NAMES } from '@/constants/routeNames'
import {
  APP_NAV_ITEMS,
  filterNavByAccess,
  groupNavItems,
  isNavItemActive,
} from '@/constants/navigation'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const route = useRoute()
const { canAccess } = usePermissions()
const ui = useUiStore()
const { sidebarCollapsed } = storeToRefs(ui)
const { sidebarLogo } = useBackOfficeLogo()
const pathNav = useRequestPathNav()

const allowedItems = computed(() => filterNavByAccess(APP_NAV_ITEMS, canAccess))
const pathKinds = computed(() =>
  allowedItems.value.filter((item) => item.pathEntries).map((item) => item.pathEntries),
)
const groups = computed(() => groupNavItems(pathNav.expand(allowedItems.value)))

pathNav.follow(pathKinds)

function isActive(item) {
  return item.path ? pathNav.isActive(item) : isNavItemActive(item, route.name)
}

const activeGroupId = computed(
  () => groups.value.find((group) => group.items.some(isActive))?.id ?? null,
)
const { isExpanded, toggle } = useSidebarGroups(activeGroupId)

function labelOf(item) {
  return item.label ?? t(item.labelKey)
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

    <div class="sidebar__scroll">
      <div
        v-for="group in groups"
        :key="group.id"
        class="sidebar__group"
        role="group"
        :aria-labelledby="`sidebar-group-${group.id}`"
      >
        <button
          :id="`sidebar-group-${group.id}`"
          type="button"
          class="sidebar__caption"
          :aria-expanded="isExpanded(group.id) ? 'true' : 'false'"
          :aria-controls="`sidebar-menu-${group.id}`"
          @click="toggle(group.id)"
        >
          <span class="sidebar__caption-text">{{ t(group.labelKey) }}</span>
          <span
            class="sidebar__chevron"
            :class="{ 'is-open': isExpanded(group.id) }"
            aria-hidden="true"
          >
            <AppIcon name="chevron-right" />
          </span>
        </button>
        <ul
          :id="`sidebar-menu-${group.id}`"
          class="sidebar__menu"
          :class="{ 'sidebar__menu--closed': !isExpanded(group.id) }"
        >
          <li v-for="item in group.items" :key="item.id" class="sidebar__entry">
            <router-link
              class="sidebar__item"
              :class="[
                { 'is-active': isActive(item) },
                item.path ? `sidebar__item--${item.path}` : '',
              ]"
              :to="{ name: item.routeName, query: item.query }"
              :aria-current="isActive(item) ? 'page' : undefined"
              :aria-label="item.accessibleLabel ?? labelOf(item)"
              :title="sidebarCollapsed ? labelOf(item) : undefined"
            >
              <AppIcon :name="item.icon" class="sidebar__icon" />
              <span class="sidebar__label">{{ labelOf(item) }}</span>
              <span v-if="item.count" class="sidebar__count" aria-hidden="true">
                <bdi>{{ item.count }}</bdi>
              </span>
            </router-link>
          </li>
        </ul>
      </div>
    </div>
  </nav>
</template>
