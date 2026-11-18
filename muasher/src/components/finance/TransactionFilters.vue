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
import {
  LIST_FILTERS,
  joinFilterValues,
  splitFilterValue,
} from '@/utils/finance/transactionFilterValues'

defineProps({
  filters: { type: Object, required: true },
  sort: { type: String, required: true },
})

const emit = defineEmits(['filter', 'sort'])
const { t } = useI18n()
const { can } = usePermissions()
const canPickEntity = computed(() => can(PERMISSIONS.ENTITIES_VIEW))

// Labels only: a status explains itself on hover in the table, not inside the filter.
function labelledOptions(group, values) {
  return values.map((value) => ({ value, label: t(`finance.operations.${group}.${value}.label`) }))
}

const listFilters = computed(() => [
  { key: 'type', group: 'types', options: labelledOptions('types', LIST_FILTERS.type) },
  { key: 'status', group: 'statuses', options: labelledOptions('statuses', LIST_FILTERS.status) },
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
