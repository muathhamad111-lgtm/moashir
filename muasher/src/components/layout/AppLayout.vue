<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import TopNavbar from './TopNavbar.vue'
import Sidebar from './Sidebar.vue'
import { useBrandingStore } from '@/stores/branding'

const { t } = useI18n()
const branding = useBrandingStore()

onMounted(() => branding.ensureLoaded())
</script>

<template>
  <div class="app-layout">
    <a class="app-layout__skip" href="#app-main">{{ t('shell.skipToContent') }}</a>
    <Sidebar />
    <div class="app-layout__main">
      <TopNavbar />
      <main id="app-main" class="app-layout__content" tabindex="-1">
        <slot />
      </main>
    </div>
  </div>
</template>
