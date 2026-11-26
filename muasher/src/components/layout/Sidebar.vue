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

// The phone navigation drawer shows a second sidebar beside the hidden in-page one: its ids differ.
// rail: the desktop sidebar. drawer: the same menu inside the phone navigation drawer: no platform
// card (logo, preview), never collapsed, every group open, and ids of its own beside the hidden rail.
const props = defineProps({
  variant: {
    type: String,
    default: 'rail',
    validator: (value) => ['rail', 'drawer'].includes(value),
  },
})
const isDrawer = computed(() => props.variant === 'drawer')
const idPrefix = computed(() => (isDrawer.value ? 'nav-drawer' : 'sidebar'))
const { t } = useI18n()
const route = useRoute()
const { canAccess } = usePermissions()
const ui = useUiStore()
const { sidebarCollapsed } = storeToRefs(ui)
const isCollapsed = computed(() => !isDrawer.value && sidebarCollapsed.value)
const pathNav = useRequestPathNav()
const { withPendingCounts } = useSidebarPendingCounts()

const allowedItems = computed(() => filterNavByAccess(APP_NAV_ITEMS, canAccess))
const pathKinds = computed(() =>
  allowedItems.value.filter((item) => item.pathEntries).map((item) => item.pathEntries),
)
const shownItems = computed(() => withPendingCounts(pathNav.expand(allowedItems.value)))
const groups = computed(() => groupNavItems(shownItems.value))
// A short menu keeps every group open under a plain heading: nothing to fold.
const isShortMenu = computed(
  () => isDrawer.value || shownItems.value.length <= SHORT_MENU_MAX_ITEMS,
)

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
    :class="{ 'sidebar--collapsed': isCollapsed, 'sidebar--drawer': isDrawer }"
    :aria-label="t('nav.sidebarLabel')"
  >
    <span v-if="!isDrawer" class="rail-glow" aria-hidden="true" />
    <SidebarIdentityCard
      v-if="!isDrawer"
      :home-label="t('shell.brandHome')"
      :collapsed="isCollapsed"
    />

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
          :aria-labelledby="`${idPrefix}-group-${group.id}`"
        >
          <p
            v-if="isShortMenu"
            :id="`${idPrefix}-group-${group.id}`"
            class="sidebar__caption sidebar__caption--static"
          >
            {{ t(group.labelKey) }}
          </p>
          <button
            v-else
            :id="`${idPrefix}-group-${group.id}`"
            type="button"
            class="sidebar__caption"
            :aria-expanded="isExpanded(group.id) ? 'true' : 'false'"
            :aria-controls="`${idPrefix}-menu-${group.id}`"
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
            :id="`${idPrefix}-menu-${group.id}`"
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
