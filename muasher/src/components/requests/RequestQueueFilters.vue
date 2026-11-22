<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSelect from '@/components/form/AppSelect.vue'
import { QUEUE_SORTS, QUEUE_STAGES } from '@/constants/accredited'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { statusLabelKey } from '@/utils/canonicalStatus'
import { stageLabelKey } from '@/utils/assessments/queueStages'
import { localizedText } from '@/utils/localizedText'
import { queueStatusesOf } from '@/utils/requestPaths'

const props = defineProps({
  path: { type: String, required: true },
  filters: { type: Object, required: true },
  sort: { type: String, required: true },
  assessors: { type: Array, default: () => [] },
  cycles: { type: Array, default: () => [] },
  stageCounts: { type: Object, default: null },
})

const emit = defineEmits(['filter', 'sort'])
const { t, locale } = useI18n()

// The request stage, each with its count once the counts are known; «all» sends no stage.
function stageLabel(stage) {
  const label = t(stageLabelKey(stage))
  const count = props.stageCounts?.[stage]
  return count === undefined ? label : t('requests.stages.withCount', { label, count })
}
const stageOptions = computed(() =>
  Object.values(QUEUE_STAGES).map((stage) => ({
    value: stage === QUEUE_STAGES.ALL ? '' : stage,
    label: stageLabel(stage),
  })),
)
const showsCycles = computed(
  () => props.path === ASSESSMENT_PATHS.ADVANCED && props.cycles.length > 0,
)
const cycleOptions = computed(() => [
  { value: '', label: t('dataList.filter.all') },
  ...props.cycles.map((cycle) => ({
    value: String(cycle.id),
    label: `${localizedText(cycle.name, locale.value)} ${cycle.year}`,
  })),
])
const statusOptions = computed(() => [
  { value: '', label: t('dataList.filter.all') },
  ...queueStatusesOf(props.path).map((value) => ({ value, label: t(statusLabelKey(value)) })),
])
const overdueOptions = computed(() => [
  { value: '', label: t('dataList.filter.all') },
  { value: 'true', label: t('requests.filters.overdueOnly') },
  { value: 'false', label: t('requests.filters.onTimeOnly') },
])
const assessorOptions = computed(() => [
  { value: '', label: t('dataList.filter.all') },
  ...props.assessors.map((assessor) => ({ value: String(assessor.id), label: assessor.name })),
])
const sortOptions = computed(() =>
  QUEUE_SORTS.map((value) => ({
    value,
    label: t(`requests.sorts.${value.replace('-', 'desc_')}`),
  })),
)

function setCycle(value) {
  emit('filter', 'status', '')
  emit('filter', 'cycleId', value)
}
</script>

<template>
  <div class="request-filters">
    <AppSelect
      :model-value="filters.stage"
      :options="stageOptions"
      :label="t('requests.stages.label')"
      data-testid="request-stage-filter"
      @update:model-value="emit('filter', 'stage', $event)"
    />
    <AppSelect
      :model-value="filters.status"
      :options="statusOptions"
      :label="t('requests.filters.status')"
      data-testid="request-status-filter"
      @update:model-value="emit('filter', 'status', $event)"
    />
    <AppSelect
      v-if="showsCycles"
      :model-value="filters.cycleId"
      :options="cycleOptions"
      :label="t('requests.filters.cycle')"
      @update:model-value="setCycle"
    />
    <AppSelect
      :model-value="filters.overdue"
      :options="overdueOptions"
      :label="t('requests.filters.overdue')"
      @update:model-value="emit('filter', 'overdue', $event)"
    />
    <AppSelect
      v-if="assessors.length"
      :model-value="filters.assessorId"
      :options="assessorOptions"
      :label="t('requests.filters.assessor')"
      @update:model-value="emit('filter', 'assessorId', $event)"
    />
    <AppSelect
      :model-value="sort"
      :options="sortOptions"
      :label="t('requests.filters.sort')"
      @update:model-value="emit('sort', $event)"
    />
  </div>
</template>
