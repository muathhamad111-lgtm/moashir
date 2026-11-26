<script setup>
import { computed, onBeforeUnmount, watch } from 'vue'
import { useRoute } from 'vue-router'
import NdaAgreementDialog from './NdaAgreementDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { useNdaGateStore } from '@/stores/ndaGate'
import { onNdaRequired, resetNdaGate } from '@/api/ndaEvents'

// The modal opens inside the signed-in shells only (never on the sign-in, MFA or password pages).
const PROMPT_LAYOUTS = ['app', 'portal']

const auth = useAuthStore()
const gate = useNdaGateStore()
const route = useRoute()

const canPrompt = computed(
  () =>
    auth.isAuthenticated &&
    route.meta.requiresAuth === true &&
    PROMPT_LAYOUTS.includes(route.meta.layout),
)

function prompt() {
  if (!gate.open) gate.show()
}

watch(
  () => canPrompt.value && auth.user?.ndaAcceptanceRequired === true,
  (due) => {
    if (due) prompt()
  },
  { immediate: true },
)

watch(
  () => auth.isAuthenticated,
  (signedIn) => {
    if (signedIn) return
    gate.open = false
    resetNdaGate()
  },
)

const stopListening = onNdaRequired(prompt)
onBeforeUnmount(stopListening)
</script>

<template>
  <NdaAgreementDialog v-if="auth.isAuthenticated" />
</template>
