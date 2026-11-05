<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSelect from '@/components/form/AppSelect.vue'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import {
  PAYMENT_METHOD_VALUES,
  PAYMENT_PURPOSE_VALUES,
  PAYMENT_STATUS_VALUES,
} from '@/constants/payments'
import { FINANCE_MODES, TRANSACTION_SORTS } from '@/constants/finance'

defineProps({
  filters: { type: Object, required: true },
  sort: { type: String, required: true },
})

const emit = defineEmits(['filter', 'sort'])
const { t } = useI18n()

function withAll(allKey, values, prefix) {
  return [
    { value: '', label: t(allKey) },
    ...values.map((value) => ({ value, label: t(`${prefix}.${value}`) })),
  ]
}

const statusOptions = computed(() =>
  withAll('finance.filters.allStatuses', PAYMENT_STATUS_VALUES, 'finance.status'),
)
const purposeOptions = computed(() =>
  withAll('finance.filters.allServices', PAYMENT_PURPOSE_VALUES, 'finance.purposes'),
)
const methodOptions = computed(() =>
  withAll('finance.filters.allMethods', PAYMENT_METHOD_VALUES, 'finance.methods'),
)
const modeOptions = computed(() => [
  { value: '', label: t('finance.filters.modes.live') },
  { value: FINANCE_MODES.TEST, label: t('finance.filters.modes.test') },
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
    <AppSelect
      variant="filter"
      :model-value="filters.status"
      :options="statusOptions"
      :label="t('finance.filters.status')"
      @update:model-value="emit('filter', 'status', $event)"
    />
    <AppSelect
      variant="filter"
      :model-value="filters.purpose"
      :options="purposeOptions"
      :label="t('finance.filters.service')"
      @update:model-value="emit('filter', 'purpose', $event)"
    />
    <AppSelect
      variant="filter"
      :model-value="filters.method"
      :options="methodOptions"
      :label="t('finance.filters.method')"
      @update:model-value="emit('filter', 'method', $event)"
    />
    <AppSelect
      variant="filter"
      :model-value="filters.mode"
      :options="modeOptions"
      :label="t('finance.filters.mode')"
      @update:model-value="emit('filter', 'mode', $event)"
    />
    <AppSelect
      variant="filter"
      :model-value="sort"
      :options="sortOptions"
      :label="t('finance.filters.sort')"
      @update:model-value="emit('sort', $event)"
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
  </div>
</template>
