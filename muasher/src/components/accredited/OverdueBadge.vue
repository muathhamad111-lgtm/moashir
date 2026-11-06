<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  overdue: { type: Boolean, required: true },
})

const { t } = useI18n()
</script>

<template>
  <Badge v-if="overdue" variant="danger" class="overdue-badge">
    <AppIcon name="clock" />
    {{ t('requests.overdue') }}
  </Badge>
  <Badge v-else variant="success" class="overdue-badge">
    <AppIcon name="check-circle" />
    {{ t('requests.onTime') }}
  </Badge>
</template>
