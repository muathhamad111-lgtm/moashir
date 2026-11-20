<script setup>
import AppLoadingScreen from './AppLoadingScreen.vue'
import { useDeferredVisibility } from '@/composables/useDeferredVisibility'

// A page waiting for its first data: the waiting page appears once the wait outlasts the
// short delay, or at once when it takes over from the page-load overlay.
const showsMark = useDeferredVisibility(() => true)
</script>

<template>
  <AppLoadingScreen variant="page" :shows-mark="showsMark" />
</template>
