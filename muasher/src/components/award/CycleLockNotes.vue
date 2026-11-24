<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  reasons: { type: Array, required: true },
})

const { t } = useI18n()
</script>

<template>
  <ul v-if="reasons.length" class="cycle-setup__locks" data-testid="cycle-lock-notes">
    <li v-for="reason in reasons" :key="reason" class="cycle-form__note">
      <AppIcon name="lock" />
      {{ t(`cycles.setup.locks.${reason}`) }}
    </li>
  </ul>
</template>
