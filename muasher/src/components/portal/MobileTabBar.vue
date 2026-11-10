<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { PORTAL_AREAS, PORTAL_TAB_ITEMS } from '@/constants/portal'
import { portalAreaOf } from '@/utils/portalAudience'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppPopover from '@/components/ui/AppPopover.vue'
import PathsSheetList from '@/components/paths/PathsSheetList.vue'

const { t } = useI18n()
const route = useRoute()
const sheetRef = ref(null)

const TAB_AREAS = PORTAL_TAB_ITEMS.map((item) => item.area)

const activeArea = computed(() => {
  const area = portalAreaOf(route.name)
  if (!area) return null
  return TAB_AREAS.includes(area) ? area : PORTAL_AREAS.MORE
})

function tabClass(item) {
  return activeArea.value === item.area ? 'mobile-tab-bar__tab is-active' : 'mobile-tab-bar__tab'
}

function bindSheet(element) {
  sheetRef.value = element
}

watch(
  () => route.fullPath,
  () => sheetRef.value?.dismiss(),
)
</script>

<template>
  <nav class="mobile-tab-bar" :aria-label="t('portal.nav.mobileLabel')">
    <ul class="mobile-tab-bar__list">
      <li v-for="item in PORTAL_TAB_ITEMS" :key="item.area" class="mobile-tab-bar__entry">
        <AppPopover
          v-if="item.opensSheet"
          :ref="bindSheet"
          class="mobile-tab-bar__sheet"
          :label="t('paths.sheet.title')"
          :trigger-label="t(item.labelKey)"
          :trigger-class="tabClass(item)"
          data-testid="paths-tab"
        >
          <template #trigger>
            <span class="mobile-tab-bar__pill">
              <AppIcon :name="item.icon" />
            </span>
            <span class="mobile-tab-bar__label">{{ t(item.labelKey) }}</span>
          </template>
          <template #default="{ close }">
            <PathsSheetList @navigate="close" />
          </template>
        </AppPopover>
        <router-link
          v-else
          :class="tabClass(item)"
          :to="{ name: item.routeName }"
          :aria-current="activeArea === item.area ? 'page' : undefined"
        >
          <span class="mobile-tab-bar__pill">
            <AppIcon :name="item.icon" />
          </span>
          <span class="mobile-tab-bar__label">{{ t(item.labelKey) }}</span>
        </router-link>
      </li>
    </ul>
  </nav>
</template>
