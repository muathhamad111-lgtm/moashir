<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  meets: { type: Boolean, default: null },
})

const { t } = useI18n()
</script>

<template>
  <Badge v-if="meets !== null" :variant="meets ? 'success' : 'danger'">
    <AppIcon :name="meets ? 'arrow-up' : 'arrow-down'" />
    {{ meets ? t('filtering.threshold.meets') : t('filtering.threshold.below') }}
  </Badge>
</template>
