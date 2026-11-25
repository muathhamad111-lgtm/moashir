<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'

// A request the entity still prepares reads «قيد الإعداد» (its progress is on the request page);
// any other shows its status.
defineProps({
  item: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <Badge v-if="item.progress" variant="muted">{{ t('requests.stages.preparing') }}</Badge>
  <StatusBadge v-else :status="item.status" />
</template>
