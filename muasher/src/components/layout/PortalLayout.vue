<script setup>
import { computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { usePortalCountsStore } from '@/stores/portalCounts'
import { useActionCenterStore } from '@/stores/actionCenter'
import { useEntityLevel } from '@/composables/useEntityLevel'
import { useLevelAchieved } from '@/composables/useLevelAchieved'
import { useSideNavCollapsed } from '@/composables/useSideNavCollapsed'
import { isMobileTaskScreen, isTaskScreen } from '@/utils/portalAudience'
import PortalSideNav from '@/components/portal/PortalSideNav.vue'
import PortalTopBar from '@/components/portal/PortalTopBar.vue'
import AttentionStrip from '@/components/portal/AttentionStrip.vue'
import MobileTabBar from '@/components/portal/MobileTabBar.vue'
import PortalFooter from '@/components/portal/PortalFooter.vue'
import DemoDataNotice from '@/components/ui/DemoDataNotice.vue'
import LevelAchievedDialog from '@/components/level/LevelAchievedDialog.vue'

const { t } = useI18n()
const route = useRoute()
const counts = usePortalCountsStore()
const actionCenter = useActionCenterStore()
const { certificate, tier } = useEntityLevel()
const achieved = useLevelAchieved(certificate)
const { collapsed, toggle } = useSideNavCollapsed()

const isTask = computed(() => isTaskScreen(route.name))
const isMobileTask = computed(() => isMobileTaskScreen(route.name))
const demoModules = computed(() => route.meta.demoModules ?? [])

onMounted(() => {
  counts.refreshAll()
  actionCenter.ensureLoaded()
})

watch(
  () => route.name,
  (name, previous) => {
    if (previous && !isTaskScreen(name)) actionCenter.load()
  },
)
</script>

<template>
  <div
    class="portal-layout"
    :class="{
      'portal-layout--task': isTask,
      'portal-layout--mobile-task': isMobileTask,
      'portal-layout--nav-collapsed': collapsed,
    }"
  >
    <a class="portal-layout__skip" href="#portal-main">{{ t('shell.skipToContent') }}</a>
    <PortalSideNav class="portal-layout__nav" :collapsed="collapsed" @toggle="toggle" />
    <div class="portal-layout__column">
      <PortalTopBar />
      <AttentionStrip v-if="!isTask" class="portal-layout__strip" />
      <main id="portal-main" class="portal-layout__main" tabindex="-1">
        <DemoDataNotice class="portal-layout__demo" :modules="demoModules" />
        <slot />
      </main>
      <PortalFooter />
    </div>
    <MobileTabBar v-if="!isMobileTask" class="portal-layout__tabs" />
    <LevelAchievedDialog
      v-if="certificate"
      :open="achieved.isOpen.value"
      :tier="tier"
      @acknowledge="achieved.acknowledge"
    />
  </div>
</template>
