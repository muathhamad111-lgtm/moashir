<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DropdownList from '@/components/ui/DropdownList.vue'
import { QUEUE_SORTS, QUEUE_STATUSES } from '@/constants/accredited'
import { ADVANCED_QUEUE_STATUSES } from '@/constants/advanced'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { statusLabelKey } from '@/utils/canonicalStatus'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  filters: { type: Object, required: true },
  sort: { type: String, required: true },
  assessors: { type: Array, default: () => [] },
  cycles: { type: Array, default: () => [] },
})

const emit = defineEmits(['filter', 'sort'])
const { t, locale } = useI18n()

const PATHS = [ASSESSMENT_PATHS.ACCREDITED, ASSESSMENT_PATHS.ADVANCED]

const statuses = computed(() => {
  if (props.filters.path === ASSESSMENT_PATHS.ACCREDITED) return QUEUE_STATUSES
  if (props.filters.path === ASSESSMENT_PATHS.ADVANCED || props.filters.cycleId)
    return ADVANCED_QUEUE_STATUSES
  return [...new Set([...QUEUE_STATUSES, ...ADVANCED_QUEUE_STATUSES])]
})
const pathOptions = computed(() => [
  { value: '', label: t('requests.filters.allPaths') },
  ...PATHS.map((value) => ({ value, label: t(`requests.paths.${value}`) })),
])
const cycleOptions = computed(() => [
  { value: '', label: t('requests.filters.allCycles') },
  ...props.cycles.map((cycle) => ({
    value: String(cycle.id),
    label: `${localizedText(cycle.name, locale.value)} ${cycle.year}`,
  })),
])
const statusOptions = computed(() => [
  { value: '', label: t('requests.filters.allStatuses') },
  ...statuses.value.map((value) => ({ value, label: t(statusLabelKey(value)) })),
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

function setPath(value) {
  emit('filter', 'status', '')
  if (value !== ASSESSMENT_PATHS.ADVANCED) emit('filter', 'cycleId', '')
  emit('filter', 'path', value)
}

function setCycle(value) {
  emit('filter', 'status', '')
  emit('filter', 'cycleId', value)
}
</script>

<template>
  <div class="request-filters">
    <DropdownList
      :model-value="filters.status"
      :options="statusOptions"
      :placeholder="t('requests.filters.status')"
      @update:model-value="emit('filter', 'status', $event)"
    />
    <DropdownList
      :model-value="filters.path"
      :options="pathOptions"
      :placeholder="t('requests.filters.path')"
      @update:model-value="setPath"
    />
    <DropdownList
      v-if="cycles.length && filters.path !== 'accredited'"
      :model-value="filters.cycleId"
      :options="cycleOptions"
      :placeholder="t('requests.filters.cycle')"
      @update:model-value="setCycle"
    />
    <DropdownList
      :model-value="filters.overdue"
      :options="overdueOptions"
      :placeholder="t('requests.filters.overdue')"
      @update:model-value="emit('filter', 'overdue', $event)"
    />
    <DropdownList
      v-if="assessors.length"
      :model-value="filters.assessorId"
      :options="assessorOptions"
      :placeholder="t('requests.filters.assessor')"
      @update:model-value="emit('filter', 'assessorId', $event)"
    />
    <DropdownList
      :model-value="sort"
      :options="sortOptions"
      :placeholder="t('requests.filters.sort')"
      @update:model-value="emit('sort', $event)"
    />
  </div>
</template>
