<script setup>
import { useRoute } from 'vue-router'
import PublicNavbar from './PublicNavbar.vue'
import AppFooter from './AppFooter.vue'
import ChatWidget from '@/components/ai/ChatWidget.vue'
import DemoDataNotice from '@/components/ui/DemoDataNotice.vue'

const route = useRoute()
</script>

<template>
  <div class="public-layout">
    <PublicNavbar />
    <main class="public-layout__content">
      <DemoDataNotice class="public-layout__demo" :modules="route.meta.demoModules" />
      <slot />
    </main>
    <AppFooter />
    <ChatWidget />
  </div>
</template>
