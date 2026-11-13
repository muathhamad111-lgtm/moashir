<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  compact: { type: Boolean, default: false },
})

const { t } = useI18n()
</script>

<template>
  <span class="coming-soon-chip" :class="{ 'coming-soon-chip--compact': compact }">
    <AppIcon v-if="!compact" name="clock" class="coming-soon-chip__icon" />
    <span>{{ t('portal.tools.soon') }}</span>
  </span>
</template>
