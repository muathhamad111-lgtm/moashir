<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSelect from '@/components/form/AppSelect.vue'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import {
  MY_PAYMENT_STATUS_GROUPS,
  PAYMENT_CHANNEL_VALUES,
  PAYMENT_METHOD_VALUES,
  PAYMENT_PURPOSE_VALUES,
} from '@/constants/payments'

defineProps({
  filters: { type: Object, required: true },
})

const emit = defineEmits(['filter'])
const { t } = useI18n()

function withAll(values, prefix) {
  return [
    { value: '', label: t('dataList.filter.all') },
    ...values.map((value) => ({ value, label: t(`${prefix}.${value}`) })),
  ]
}

const statusOptions = computed(() => [
  { value: '', label: t('dataList.filter.all') },
  ...Object.entries(MY_PAYMENT_STATUS_GROUPS).map(([group, statuses]) => ({
    value: statuses.join(','),
    label: t(`portal.payments.statusGroups.${group}`),
  })),
])
const purposeOptions = computed(() => withAll(PAYMENT_PURPOSE_VALUES, 'finance.purposes'))
const channelOptions = computed(() => withAll(PAYMENT_CHANNEL_VALUES, 'finance.channels'))
const methodOptions = computed(() => withAll(PAYMENT_METHOD_VALUES, 'finance.methods'))
</script>

<template>
  <div class="my-payment-filters" data-testid="my-payment-filters">
    <AppSelect
      :model-value="filters.status"
      :options="statusOptions"
      :label="t('finance.filters.status')"
      @update:model-value="emit('filter', 'status', $event)"
    />
    <AppSelect
      :model-value="filters.purpose"
      :options="purposeOptions"
      :label="t('finance.filters.service')"
      @update:model-value="emit('filter', 'purpose', $event)"
    />
    <AppSelect
      :model-value="filters.channel"
      :options="channelOptions"
      :label="t('finance.filters.channel')"
      @update:model-value="emit('filter', 'channel', $event)"
    />
    <AppSelect
      :model-value="filters.method"
      :options="methodOptions"
      :label="t('finance.filters.method')"
      @update:model-value="emit('filter', 'method', $event)"
    />
    <BaseDatePicker
      :model-value="filters.from"
      name="my-payments-from"
      :label="t('finance.filters.from')"
      :max="filters.to"
      @update:model-value="emit('filter', 'from', $event)"
    />
    <BaseDatePicker
      :model-value="filters.to"
      name="my-payments-to"
      :label="t('finance.filters.to')"
      :min="filters.from"
      @update:model-value="emit('filter', 'to', $event)"
    />
  </div>
</template>
