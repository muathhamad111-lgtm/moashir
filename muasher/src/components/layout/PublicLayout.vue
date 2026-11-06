<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import PublicNavbar from './PublicNavbar.vue'
import AppFooter from './AppFooter.vue'
import ChatWidget from '@/components/ai/ChatWidget.vue'
import { SHOWS_DEMO_ONLY_MODULES } from '@/constants/buildPosture'
import DemoDataNotice from '@/components/ui/DemoDataNotice.vue'

const route = useRoute()
const showsAssistant = computed(
  () => SHOWS_DEMO_ONLY_MODULES && route.matched.length > 0 && route.meta.hideAssistant !== true,
)
</script>

<template>
  <div class="public-layout">
    <PublicNavbar />
    <main class="public-layout__content">
      <DemoDataNotice class="public-layout__demo" :modules="route.meta.demoModules" />
      <slot />
    </main>
    <AppFooter />
    <ChatWidget v-if="showsAssistant" />
  </div>
</template>
