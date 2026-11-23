<script setup>
import { computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import AccountCompletionDialog from './AccountCompletionDialog.vue'
import FileAccessRefusalDialog from './FileAccessRefusalDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { useAccountCompletionStore } from '@/stores/accountCompletion'

// The modal asks after each sign-in, inside the signed-in shells only (never on sign-in, MFA or password pages).
const PROMPT_LAYOUTS = ['app', 'portal']

const auth = useAuthStore()
const completion = useAccountCompletionStore()
const route = useRoute()

// The mandatory staff agreement comes first; the skippable completion modal waits until it is accepted.
const canPrompt = computed(
  () =>
    auth.isAuthenticated &&
    auth.user?.ndaAcceptanceRequired !== true &&
    route.meta.requiresAuth === true &&
    PROMPT_LAYOUTS.includes(route.meta.layout),
)

watch(
  () => canPrompt.value && completion.shouldPrompt,
  (prompt) => {
    if (prompt) completion.open()
  },
  { immediate: true },
)
</script>

<template>
  <template v-if="auth.isAuthenticated">
    <AccountCompletionDialog />
    <FileAccessRefusalDialog />
  </template>
</template>
