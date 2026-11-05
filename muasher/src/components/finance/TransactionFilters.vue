<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DropdownList from '@/components/ui/DropdownList.vue'
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
    <DropdownList
      :model-value="filters.status"
      :options="statusOptions"
      :placeholder="t('finance.filters.status')"
      @update:model-value="emit('filter', 'status', $event)"
    />
    <DropdownList
      :model-value="filters.purpose"
      :options="purposeOptions"
      :placeholder="t('finance.filters.service')"
      @update:model-value="emit('filter', 'purpose', $event)"
    />
    <DropdownList
      :model-value="filters.method"
      :options="methodOptions"
      :placeholder="t('finance.filters.method')"
      @update:model-value="emit('filter', 'method', $event)"
    />
    <DropdownList
      :model-value="filters.mode"
      :options="modeOptions"
      :placeholder="t('finance.filters.mode')"
      @update:model-value="emit('filter', 'mode', $event)"
    />
    <DropdownList
      :model-value="sort"
      :options="sortOptions"
      :placeholder="t('finance.filters.sort')"
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
