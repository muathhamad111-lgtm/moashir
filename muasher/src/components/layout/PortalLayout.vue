<script setup>
import { computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { usePortalCountsStore } from '@/stores/portalCounts'
import { useActionCenterStore } from '@/stores/actionCenter'
import { isMobileTaskScreen, isTaskScreen } from '@/utils/portalAudience'
import PortalTopBar from '@/components/portal/PortalTopBar.vue'
import AttentionStrip from '@/components/portal/AttentionStrip.vue'
import MobileTabBar from '@/components/portal/MobileTabBar.vue'
import PortalFooter from '@/components/portal/PortalFooter.vue'
import DemoDataNotice from '@/components/ui/DemoDataNotice.vue'

const { t } = useI18n()
const route = useRoute()
const counts = usePortalCountsStore()
const actionCenter = useActionCenterStore()

const isTask = computed(() => isTaskScreen(route.name))
const isMobileTask = computed(() => isMobileTaskScreen(route.name))
const demoModules = computed(() => route.meta.demoModules ?? [])

onMounted(() => counts.refreshAll())

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
    :class="{ 'portal-layout--task': isTask, 'portal-layout--mobile-task': isMobileTask }"
  >
    <a class="portal-layout__skip" href="#portal-main">{{ t('shell.skipToContent') }}</a>
    <PortalTopBar />
    <AttentionStrip v-if="!isTask" class="portal-layout__strip" />
    <main id="portal-main" class="portal-layout__main" tabindex="-1">
      <DemoDataNotice class="portal-layout__demo" :modules="demoModules" />
      <slot />
    </main>
    <PortalFooter />
    <MobileTabBar v-if="!isMobileTask" class="portal-layout__tabs" />
  </div>
</template>
