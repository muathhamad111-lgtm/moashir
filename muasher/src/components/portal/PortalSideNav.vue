<script setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { useEntityLevel } from '@/composables/useEntityLevel'
import { usePortalCountsStore } from '@/stores/portalCounts'
import { PORTAL_SIDE_NAV_ITEMS, PORTAL_TOOL_ROUTES, PORTAL_TOOLS } from '@/constants/portal'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { accessFor } from '@/constants/routeAccess'
import { usePermissions } from '@/composables/usePermissions'
import AppIcon from '@/components/ui/AppIcon.vue'
import LevelMiniCard from '@/components/level/LevelMiniCard.vue'
import PortalSideNavItem from './PortalSideNavItem.vue'

const props = defineProps({
  collapsed: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle'])
const { t } = useI18n()
const route = useRoute()
const { logoOnDark } = useBrandLogo()
const { tier, miniCard } = useEntityLevel()
const counts = storeToRefs(usePortalCountsStore())
const toolsId = useId()
const { canAccess } = usePermissions()
const tools = computed(() => PORTAL_TOOLS.filter((tool) => canAccess(accessFor(tool.routeName))))
const onToolPage = computed(() => PORTAL_TOOL_ROUTES.includes(route.name))
const toolsOpen = ref(onToolPage.value)
const showTools = computed(() => toolsOpen.value || onToolPage.value)

function countOf(item) {
  return item.badge ? (counts[item.badge]?.value ?? 0) : 0
}

function isActive(item) {
  return item.routes.includes(route.name)
}

function toggleLabel() {
  return props.collapsed ? t('portal.sideNav.expand') : t('portal.sideNav.collapse')
}
</script>

<template>
  <nav
    class="side-nav"
    :class="{ 'side-nav--collapsed': collapsed }"
    :aria-label="t('portal.nav.label')"
    data-testid="portal-side-nav"
  >
    <router-link class="side-nav__brand" :to="{ name: ROUTE_NAMES.APP_DASHBOARD }">
      <img class="side-nav__logo" :src="logoOnDark" :alt="t('portal.brandHome')" />
    </router-link>

    <ul class="side-nav__list">
      <li v-for="item in PORTAL_SIDE_NAV_ITEMS" :key="item.key">
        <PortalSideNavItem
          :to="{ name: item.routeName }"
          :icon="item.icon"
          :label="t(`portal.sideNav.items.${item.key}`)"
          :active="isActive(item)"
          :count="countOf(item)"
          :count-label="
            item.badge
              ? t(`portal.sideNav.unread.${item.key}`, { count: countOf(item) }, countOf(item))
              : ''
          "
          :collapsed="collapsed"
        />
      </li>
    </ul>

    <div class="side-nav__group">
      <button
        type="button"
        class="side-nav__group-toggle"
        :class="{ 'side-nav__group-toggle--collapsed': collapsed }"
        :aria-expanded="showTools"
        :aria-controls="toolsId"
        :title="collapsed ? t('portal.tools.group') : undefined"
        data-testid="portal-tools-toggle"
        @click="toolsOpen = !showTools"
      >
        <span class="side-nav__group-icon">
          <AppIcon name="layers" />
        </span>
        <span :class="collapsed ? 'side-nav-item__sr' : 'side-nav__group-label'">
          {{ t('portal.tools.group') }}
        </span>
        <AppIcon
          v-if="!collapsed"
          class="side-nav__group-chevron"
          :name="showTools ? 'chevron-up' : 'chevron-down'"
        />
      </button>
      <ul v-show="showTools" :id="toolsId" class="side-nav__list side-nav__list--tools">
        <li v-for="tool in tools" :key="tool.key">
          <PortalSideNavItem
            :to="{ name: tool.routeName }"
            :icon="tool.icon"
            :label="t(`portal.tools.items.${tool.key}.title`)"
            :active="route.name === tool.routeName"
            :collapsed="collapsed"
            :soon="!tool.live"
          />
        </li>
      </ul>
    </div>

    <div class="side-nav__footer">
      <LevelMiniCard :tier="tier" :action="miniCard" :collapsed="collapsed" />
      <button
        type="button"
        class="side-nav__collapse"
        :aria-expanded="!collapsed"
        :aria-label="toggleLabel()"
        data-testid="portal-nav-collapse"
        @click="emit('toggle')"
      >
        <AppIcon :name="collapsed ? 'chevron-right' : 'chevron-left'" />
        <span v-if="!collapsed">{{ t('portal.sideNav.collapse') }}</span>
      </button>
    </div>
  </nav>
</template>
