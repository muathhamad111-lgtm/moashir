<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSelect from '@/components/form/AppSelect.vue'
import { QUEUE_SORTS } from '@/constants/accredited'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { statusLabelKey } from '@/utils/canonicalStatus'
import { localizedText } from '@/utils/localizedText'
import { queueStatusesOf } from '@/utils/requestPaths'

const props = defineProps({
  path: { type: String, required: true },
  filters: { type: Object, required: true },
  sort: { type: String, required: true },
  assessors: { type: Array, default: () => [] },
  cycles: { type: Array, default: () => [] },
})

const emit = defineEmits(['filter', 'sort'])
const { t, locale } = useI18n()

const showsCycles = computed(
  () => props.path === ASSESSMENT_PATHS.ADVANCED && props.cycles.length > 0,
)
const cycleOptions = computed(() => [
  { value: '', label: t('requests.filters.allCycles') },
  ...props.cycles.map((cycle) => ({
    value: String(cycle.id),
    label: `${localizedText(cycle.name, locale.value)} ${cycle.year}`,
  })),
])
const statusOptions = computed(() => [
  { value: '', label: t('requests.filters.allStatuses') },
  ...queueStatusesOf(props.path).map((value) => ({ value, label: t(statusLabelKey(value)) })),
])
const overdueOptions = computed(() => [
  { value: '', label: t('requests.filters.allRequests') },
  { value: 'true', label: t('requests.filters.overdueOnly') },
  { value: 'false', label: t('requests.filters.onTimeOnly') },
])
const assessorOptions = computed(() => [
  { value: '', label: t('requests.filters.allAssessors') },
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
      variant="filter"
      :model-value="filters.status"
      :options="statusOptions"
      :label="t('requests.filters.status')"
      @update:model-value="emit('filter', 'status', $event)"
    />
    <AppSelect
      variant="filter"
      v-if="showsCycles"
      :model-value="filters.cycleId"
      :options="cycleOptions"
      :label="t('requests.filters.cycle')"
      @update:model-value="setCycle"
    />
    <AppSelect
      variant="filter"
      :model-value="filters.overdue"
      :options="overdueOptions"
      :label="t('requests.filters.overdue')"
      @update:model-value="emit('filter', 'overdue', $event)"
    />
    <AppSelect
      variant="filter"
      v-if="assessors.length"
      :model-value="filters.assessorId"
      :options="assessorOptions"
      :label="t('requests.filters.assessor')"
      @update:model-value="emit('filter', 'assessorId', $event)"
    />
    <AppSelect
      variant="filter"
      :model-value="sort"
      :options="sortOptions"
      :label="t('requests.filters.sort')"
      @update:model-value="emit('sort', $event)"
    />
  </div>
</template>
