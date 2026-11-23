<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { usePermissions } from '@/composables/usePermissions'
import { useRequestPathNav } from '@/composables/useRequestPathNav'
import { useSidebarGroups } from '@/composables/useSidebarGroups'
import { useSidebarPendingCounts } from '@/composables/useSidebarPendingCounts'
import { useUiStore } from '@/stores/ui'
import { useSidebarTooltip } from '@/composables/useSidebarTooltip'
import {
  APP_NAV_ITEMS,
  filterNavByAccess,
  groupNavItems,
  isNavItemActive,
  SHORT_MENU_MAX_ITEMS,
} from '@/constants/navigation'
import AppIcon from '@/components/ui/AppIcon.vue'
import SidebarIdentityCard from './SidebarIdentityCard.vue'
import SidebarItem from './SidebarItem.vue'

const { t } = useI18n()
const route = useRoute()
const { canAccess } = usePermissions()
const ui = useUiStore()
const { sidebarCollapsed } = storeToRefs(ui)
const pathNav = useRequestPathNav()
const { withPendingCounts } = useSidebarPendingCounts()

const allowedItems = computed(() => filterNavByAccess(APP_NAV_ITEMS, canAccess))
const pathKinds = computed(() =>
  allowedItems.value.filter((item) => item.pathEntries).map((item) => item.pathEntries),
)
const shownItems = computed(() => withPendingCounts(pathNav.expand(allowedItems.value)))
const groups = computed(() => groupNavItems(shownItems.value))
// A short menu keeps every group open under a plain heading: nothing to fold.
const isShortMenu = computed(() => shownItems.value.length <= SHORT_MENU_MAX_ITEMS)

pathNav.follow(pathKinds)

function isActive(item) {
  return item.path ? pathNav.isActive(item) : isNavItemActive(item, route.name)
}

const activeGroupId = computed(
  () => groups.value.find((group) => group.items.some(isActive))?.id ?? null,
)
const sidebarGroups = useSidebarGroups(activeGroupId)
const isExpanded = (id) => isShortMenu.value || sidebarGroups.isExpanded(id)
const tooltip = useSidebarTooltip()

function onHint(label, element) {
  if (label) tooltip.show(label, element)
  else tooltip.hide()
}
</script>

<template>
  <nav
    class="sidebar"
    :class="{ 'sidebar--collapsed': sidebarCollapsed }"
    :aria-label="t('nav.sidebarLabel')"
  >
    <span class="rail-glow" aria-hidden="true" />
    <SidebarIdentityCard :home-label="t('shell.brandHome')" :collapsed="sidebarCollapsed" />

    <div class="sidebar__scroll" @scroll.passive="tooltip.hide">
      <template v-for="group in groups" :key="group.id">
        <ul v-if="group.headless" class="sidebar__menu sidebar__menu--headless">
          <li v-for="item in group.items" :key="item.id" class="sidebar__entry">
            <SidebarItem :item="item" :active="isActive(item)" @hint="onHint" />
          </li>
        </ul>
        <div
          v-else
          class="sidebar__group"
          role="group"
          :aria-labelledby="`sidebar-group-${group.id}`"
        >
          <p
            v-if="isShortMenu"
            :id="`sidebar-group-${group.id}`"
            class="sidebar__caption sidebar__caption--static"
          >
            {{ t(group.labelKey) }}
          </p>
          <button
            v-else
            :id="`sidebar-group-${group.id}`"
            type="button"
            class="sidebar__caption"
            :aria-expanded="isExpanded(group.id) ? 'true' : 'false'"
            :aria-controls="`sidebar-menu-${group.id}`"
            @click="sidebarGroups.toggle(group.id)"
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
              <SidebarItem :item="item" :active="isActive(item)" @hint="onHint" />
            </li>
          </ul>
        </div>
      </template>
    </div>
    <Teleport to="body">
      <span
        v-if="tooltip.hint.value"
        class="sidebar-tooltip"
        role="tooltip"
        :style="tooltip.style.value"
      >
        {{ tooltip.hint.value.label }}
      </span>
    </Teleport>
  </nav>
</template>
