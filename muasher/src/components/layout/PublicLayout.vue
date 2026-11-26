<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import PublicNavbar from './PublicNavbar.vue'
import AppFooter from './AppFooter.vue'
import ChatWidget from '@/components/ai/ChatWidget.vue'
import AppLoadingScreen from '@/components/feedback/AppLoadingScreen.vue'
import { usePageLoading } from '@/composables/usePageLoading'
import { SHOWS_MODULES_AWAITING_BACKEND } from '@/constants/buildPosture'

const route = useRoute()
const isLoadingPage = usePageLoading()
const showsAssistant = computed(
  () =>
    SHOWS_MODULES_AWAITING_BACKEND && route.matched.length > 0 && route.meta.hideAssistant !== true,
)
</script>

<template>
  <div class="public-layout">
    <PublicNavbar />
    <main class="public-layout__content" :aria-busy="isLoadingPage">
      <slot />
      <AppLoadingScreen v-if="isLoadingPage" variant="overlay" />
    </main>
    <AppFooter />
    <ChatWidget v-if="showsAssistant" />
  </div>
</template>
