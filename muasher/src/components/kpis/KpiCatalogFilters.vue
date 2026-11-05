<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DropdownList from '@/components/ui/DropdownList.vue'
import { KPI_PUBLIC_SORTS, KPI_STATUS_KEYS, KPI_YEAR_FILTER_SPAN } from '@/constants/kpis'
import { ALL } from '@/composables/useKpiFilters'

const props = defineProps({
  filters: { type: Object, required: true },
  types: { type: Array, default: () => [] },
  localized: { type: Function, required: true },
})

const emit = defineEmits(['change'])
const { t } = useI18n()

const currentYear = new Date().getFullYear()

const statusOptions = computed(() => [
  { value: ALL, label: t('kpis.page.filters.allStatuses') },
  ...KPI_STATUS_KEYS.map((key) => ({ value: key, label: t(`kpis.statuses.${key}`) })),
])

const typeOptions = computed(() => [
  { value: ALL, label: t('kpis.page.filters.allTypes') },
  ...props.types.map((type) => ({ value: type.id, label: props.localized(type.name) })),
])

const yearOptions = computed(() => [
  { value: ALL, label: t('kpis.page.filters.allYears') },
  ...Array.from({ length: KPI_YEAR_FILTER_SPAN }, (_, index) => {
    const year = currentYear - index
    return { value: year, label: String(year) }
  }),
])

const sortOptions = computed(() =>
  KPI_PUBLIC_SORTS.map((sort) => ({
    value: sort.value,
    label: t(`kpis.page.sort.${sort.labelKey}`),
  })),
)
</script>

<template>
  <div class="kpis-page__filters">
    <label class="kpis-page__filter">
      <span class="kpis-page__filter-label">{{ t('kpis.page.filters.status') }}</span>
      <DropdownList
        :model-value="filters.status"
        :options="statusOptions"
        @update:model-value="emit('change', 'status', $event)"
      />
    </label>
    <label v-if="types.length" class="kpis-page__filter">
      <span class="kpis-page__filter-label">{{ t('kpis.page.filters.type') }}</span>
      <DropdownList
        :model-value="filters.type"
        :options="typeOptions"
        @update:model-value="emit('change', 'type', $event)"
      />
    </label>
    <label class="kpis-page__filter">
      <span class="kpis-page__filter-label">{{ t('kpis.page.filters.year') }}</span>
      <DropdownList
        :model-value="filters.year"
        :options="yearOptions"
        @update:model-value="emit('change', 'year', $event)"
      />
    </label>
    <label class="kpis-page__filter">
      <span class="kpis-page__filter-label">{{ t('kpis.page.filters.owner') }}</span>
      <input
        class="field__control kpis-page__owner"
        type="search"
        name="kpiOwner"
        :value="filters.owner"
        :placeholder="t('kpis.page.filters.ownerPlaceholder')"
        @input="emit('change', 'owner', $event.target.value)"
      />
    </label>
    <label class="kpis-page__filter">
      <span class="kpis-page__filter-label">{{ t('kpis.page.sort.label') }}</span>
      <DropdownList
        :model-value="filters.sort"
        :options="sortOptions"
        @update:model-value="emit('change', 'sort', $event)"
      />
    </label>
  </div>
</template>
