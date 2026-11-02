<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'

defineProps({
  meets: { type: Boolean, default: null },
})

const { t } = useI18n()
</script>

<template>
  <Badge v-if="meets !== null" :variant="meets ? 'success' : 'danger'">
    <i :class="meets ? 'fa-solid fa-arrow-up' : 'fa-solid fa-arrow-down'" aria-hidden="true"></i>
    {{ meets ? t('filtering.threshold.meets') : t('filtering.threshold.below') }}
  </Badge>
</template>
