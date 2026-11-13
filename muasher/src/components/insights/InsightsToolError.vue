<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'

const props = defineProps({
  status: { type: String, required: true },
  reason: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t, te } = useI18n()
const message = computed(() => {
  if (props.status === 'missing') return t('insights.errors.missing')
  const key = `insights.errors.${props.reason}`
  return props.reason && te(key) ? t(key) : t('insights.errors.notEligible')
})
</script>

<template>
  <ErrorState v-if="status === 'error'" :message="t('insights.loadError')" @retry="emit('retry')" />
  <EmptyState
    v-else
    icon="lock"
    :title="t('insights.errors.notEligible')"
    :message="message"
    data-testid="insights-ineligible"
  />
</template>
