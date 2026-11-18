<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ProgressMeter from '@/components/ui/ProgressMeter.vue'
import { formatNumber } from '@/utils/formatNumber'

const props = defineProps({
  progress: { type: Object, default: null },
})

const { t, n } = useI18n()
const remaining = computed(() =>
  props.progress ? Math.max(props.progress.required - props.progress.scored, 0) : 0,
)
const label = computed(() =>
  remaining.value === 0
    ? t('requests.assigned.progress.done')
    : t('requests.assigned.progress.remaining', { count: formatNumber(n, remaining.value) }),
)
</script>

<template>
  <ProgressMeter
    v-if="progress?.required"
    class="assigned-request__progress"
    :label="label"
    :current="progress.scored"
    :total="progress.required"
  />
</template>
