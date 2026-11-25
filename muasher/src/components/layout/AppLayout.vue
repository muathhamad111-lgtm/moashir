<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import TopNavbar from './TopNavbar.vue'
import Sidebar from './Sidebar.vue'
import AppTabBar from './AppTabBar.vue'
import { useBrandingStore } from '@/stores/branding'
import AppLoadingScreen from '@/components/feedback/AppLoadingScreen.vue'
import AccountCompletionBanner from '@/components/identity/completion/AccountCompletionBanner.vue'
import { usePageLoading } from '@/composables/usePageLoading'
import { useCompactViewport } from '@/composables/useCompactViewport'

const { t } = useI18n()
const branding = useBrandingStore()
const isLoadingPage = usePageLoading()
// Phones (below md) navigate with the bottom tab bar; the sidebar takes over from md.
const isPhone = useCompactViewport()

onMounted(() => branding.ensureLoaded())
</script>

<template>
  <div class="app-layout">
    <a class="app-layout__skip" href="#app-main">{{ t('shell.skipToContent') }}</a>
    <TopNavbar />
    <div class="app-layout__body">
      <Sidebar />
      <main id="app-main" class="app-layout__content" tabindex="-1" :aria-busy="isLoadingPage">
        <AccountCompletionBanner />
        <slot />
        <AppLoadingScreen v-if="isLoadingPage" variant="overlay" />
      </main>
    </div>
    <AppTabBar v-if="isPhone" />
  </div>
</template>
