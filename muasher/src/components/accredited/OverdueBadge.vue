<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'

defineProps({
  overdue: { type: Boolean, required: true },
})

const { t } = useI18n()
</script>

<template>
  <Badge v-if="overdue" variant="danger" class="overdue-badge">
    <i class="fa-solid fa-clock" aria-hidden="true"></i>
    {{ t('requests.overdue') }}
  </Badge>
  <Badge v-else variant="success" class="overdue-badge">
    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
    {{ t('requests.onTime') }}
  </Badge>
</template>
