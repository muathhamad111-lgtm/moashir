<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ProgressMeter from '@/components/ui/ProgressMeter.vue'

// A request the entity still prepares shows «قيد الإعداد» and how far the entity got; any other shows its status.
defineProps({
  item: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <span v-if="item.progress" class="request-queue__preparing">
    <Badge variant="muted">{{ t('requests.stages.preparing') }}</Badge>
    <ProgressMeter
      class="request-queue__progress"
      :label="t('requests.progressLabel')"
      :current="item.progress.complete"
      :total="item.progress.total"
    />
  </span>
  <StatusBadge v-else :status="item.status" />
</template>
