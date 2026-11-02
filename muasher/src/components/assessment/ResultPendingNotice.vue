<script setup>
import { useI18n } from 'vue-i18n'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'

defineProps({
  result: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <div class="result-pending">
    <EmptyState
      icon="fa-regular fa-hourglass-half"
      :title="t('assessment.result.pendingTitle')"
      :message="t('assessment.result.pendingMessage')"
    >
      <template #action>
        <div class="result-pending__status">
          <span>{{ t('assessment.result.currentStatus') }}</span>
          <StatusBadge :status="result.status" />
        </div>
        <slot />
      </template>
    </EmptyState>
  </div>
</template>
