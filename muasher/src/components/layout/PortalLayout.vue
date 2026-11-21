<script setup>
import { computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { usePortalCountsStore } from '@/stores/portalCounts'
import { useActionCenterStore } from '@/stores/actionCenter'
import { useBrandingStore } from '@/stores/branding'
import { useEntityLevel } from '@/composables/useEntityLevel'
import { useLevelAchieved } from '@/composables/useLevelAchieved'
import { useSideNavCollapsed } from '@/composables/useSideNavCollapsed'
import { isMobileTaskScreen, isTaskScreen } from '@/utils/portalAudience'
import PortalSideNav from '@/components/portal/PortalSideNav.vue'
import PortalTopBar from '@/components/portal/PortalTopBar.vue'
import ProfileCompletionBanner from '@/components/portal/ProfileCompletionBanner.vue'
import MobileTabBar from '@/components/portal/MobileTabBar.vue'
import PortalFooter from '@/components/portal/PortalFooter.vue'
import LevelAchievedDialog from '@/components/level/LevelAchievedDialog.vue'
import AppLoadingScreen from '@/components/feedback/AppLoadingScreen.vue'
import { usePageLoading } from '@/composables/usePageLoading'

const { t } = useI18n()
const route = useRoute()
const counts = usePortalCountsStore()
const actionCenter = useActionCenterStore()
const branding = useBrandingStore()
const { certificate, tier } = useEntityLevel()
const achieved = useLevelAchieved(certificate)
const { collapsed, toggle } = useSideNavCollapsed()
const isLoadingPage = usePageLoading()

const isTask = computed(() => isTaskScreen(route.name))
const isMobileTask = computed(() => isMobileTaskScreen(route.name))

onMounted(() => {
  branding.ensureLoaded()
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
    <PortalTopBar :nav-collapsed="collapsed" @toggle-nav="toggle" />
    <div class="portal-layout__body">
      <PortalSideNav class="portal-layout__nav" :collapsed="collapsed" />
      <div class="portal-layout__column">
        <ProfileCompletionBanner v-if="!isTask" class="portal-layout__strip" />
        <main id="portal-main" class="portal-layout__main" tabindex="-1" :aria-busy="isLoadingPage">
          <slot />
          <AppLoadingScreen v-if="isLoadingPage" variant="overlay" />
        </main>
        <PortalFooter />
      </div>
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
