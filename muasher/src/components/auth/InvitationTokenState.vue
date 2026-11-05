<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  state: { type: String, required: true },
})

const { t } = useI18n()
</script>

<template>
  <div class="auth-status auth-status--danger" role="alert">
    <AppIcon :name="state === 'expired' ? 'clock' : 'unlink'" class="auth-status__icon" />
    <p class="auth-status__title">{{ t(`auth.invitation.states.${state}.title`) }}</p>
    <p class="auth-status__text">{{ t(`auth.invitation.states.${state}.message`) }}</p>
  </div>
</template>
