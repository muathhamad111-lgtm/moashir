<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSelect from '@/components/form/AppSelect.vue'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import AppCombobox from '@/components/form/AppCombobox.vue'
import TransactionEntityFilter from '@/components/finance/TransactionEntityFilter.vue'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { PAYMENT_PURPOSE_VALUES } from '@/constants/payments'
import { TRANSACTION_SORTS } from '@/constants/finance'
import { INTEGRATION_MODES } from '@/constants/integrations'
import { channelFigures, statusCountOf } from '@/utils/finance/overviewStats'
import { withLatinNumerals } from '@/utils/numerals'
import {
  LIST_FILTERS,
  joinFilterValues,
  splitFilterValue,
} from '@/utils/finance/transactionFilterValues'

const props = defineProps({
  filters: { type: Object, required: true },
  sort: { type: String, required: true },
  // The figures behind each type and status count (none while they load).
  stats: { type: Object, default: null },
})

const emit = defineEmits(['filter', 'sort'])
const { t, locale } = useI18n()
const { can } = usePermissions()
const canPickEntity = computed(() => can(PERMISSIONS.ENTITIES_VIEW))

const COUNTS = {
  types: (stats, value) => channelFigures(stats, value).count,
  statuses: (stats, value) => statusCountOf(stats, value),
}

// Each type and status with how many operations it has; a status explains itself on hover in the
// table, not inside the filter.
function labelledOptions(group, values, stats) {
  return values.map((value) => {
    const label = t(`finance.operations.${group}.${value}.label`)
    if (!stats) return { value, label }
    const count = new Intl.NumberFormat(withLatinNumerals(locale.value)).format(
      COUNTS[group](stats, value),
    )
    return { value, label: t('finance.filters.optionCount', { label, count }) }
  })
}

const listFilters = computed(() => [
  {
    key: 'type',
    group: 'types',
    options: labelledOptions('types', LIST_FILTERS.type, props.stats),
  },
  {
    key: 'status',
    group: 'statuses',
    options: labelledOptions('statuses', LIST_FILTERS.status, props.stats),
  },
])

function emitList(key, values) {
  emit('filter', key, joinFilterValues(values, LIST_FILTERS[key]))
}
// The API lists both modes unless one is asked for, and the totals follow the same rows.
const modeOptions = computed(() => [
  { value: '', label: t('dataList.filter.all') },
  ...[INTEGRATION_MODES.LIVE, INTEGRATION_MODES.TEST].map((value) => ({
    value,
    label: t(`finance.filters.modes.${value}`),
  })),
])
const purposeOptions = computed(() => [
  { value: '', label: t('dataList.filter.all') },
  ...PAYMENT_PURPOSE_VALUES.map((value) => ({ value, label: t(`finance.purposes.${value}`) })),
])
const sortOptions = computed(() =>
  TRANSACTION_SORTS.map((value) => ({
    value,
    label: t(`finance.sorts.${value.replace('-', 'desc_')}`),
  })),
)
</script>

<template>
  <div class="transaction-filters">
    <AppCombobox
      v-for="list in listFilters"
      :key="list.key"
      :class="`transaction-filters__${list.key}`"
      :model-value="splitFilterValue(filters[list.key])"
      :options="list.options"
      :label="t(`finance.operations.${list.group}.title`)"
      :placeholder="t('dataList.filter.all')"
      multiple
      @update:model-value="emitList(list.key, $event)"
    />
    <BaseDatePicker
      :model-value="filters.from"
      name="transactions-from"
      :label="t('finance.filters.from')"
      :max="filters.to"
      @update:model-value="emit('filter', 'from', $event)"
    />
    <BaseDatePicker
      :model-value="filters.to"
      name="transactions-to"
      :label="t('finance.filters.to')"
      :min="filters.from"
      @update:model-value="emit('filter', 'to', $event)"
    />
    <AppSelect
      :model-value="filters.purpose"
      :options="purposeOptions"
      :label="t('finance.filters.service')"
      @update:model-value="emit('filter', 'purpose', $event)"
    />
    <AppSelect
      class="transaction-filters__mode"
      :model-value="filters.mode"
      :options="modeOptions"
      :label="t('finance.filters.mode')"
      @update:model-value="emit('filter', 'mode', $event)"
    />
    <TransactionEntityFilter
      v-if="canPickEntity"
      :model-value="filters.entity_id"
      @update:model-value="emit('filter', 'entity_id', $event)"
    />
    <AppSelect
      :model-value="sort"
      :options="sortOptions"
      :label="t('finance.filters.sort')"
      @update:model-value="emit('sort', $event)"
    />
  </div>
</template>
