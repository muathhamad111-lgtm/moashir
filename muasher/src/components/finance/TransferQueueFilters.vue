<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSelect from '@/components/form/AppSelect.vue'
import { PAYMENT_PURPOSE_VALUES } from '@/constants/payments'
import { FINANCE_MODES } from '@/constants/finance'
import { FROZEN_FILTER_VALUES, TRANSFER_SORTS } from '@/constants/bankTransfer'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  filters: { type: Object, required: true },
  sort: { type: String, required: true },
  accounts: { type: Array, default: () => [] },
})

const emit = defineEmits(['filter', 'sort'])
const { t, locale } = useI18n()

const accountOptions = computed(() => [
  { value: '', label: t('finance.transfers.filters.allAccounts') },
  ...props.accounts.map((account) => ({
    value: account.id,
    label: localizedText(account.bankName, locale.value),
  })),
])
const purposeOptions = computed(() => [
  { value: '', label: t('finance.filters.allServices') },
  ...PAYMENT_PURPOSE_VALUES.map((value) => ({ value, label: t(`finance.purposes.${value}`) })),
])
const modeOptions = computed(() => [
  { value: '', label: t('finance.transfers.filters.allModes') },
  ...Object.values(FINANCE_MODES).map((value) => ({
    value,
    label: t(`finance.filters.modes.${value}`),
  })),
])
const frozenOptions = computed(() =>
  FROZEN_FILTER_VALUES.map((value) => ({
    value,
    label: t(`finance.transfers.filters.frozen.${value}`),
  })),
)
const sortOptions = computed(() =>
  TRANSFER_SORTS.map((value) => ({
    value,
    label: t(`finance.transfers.sorts.${value.startsWith('-') ? 'newest' : 'oldest'}`),
  })),
)
</script>

<template>
  <div class="transfer-filters">
    <AppSelect
      variant="filter"
      :model-value="filters.bankAccount"
      :options="accountOptions"
      :label="t('finance.transfers.filters.account')"
      @update:model-value="emit('filter', 'bankAccount', $event)"
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
      :model-value="filters.mode"
      :options="modeOptions"
      :label="t('finance.filters.mode')"
      @update:model-value="emit('filter', 'mode', $event)"
    />
    <AppSelect
      variant="filter"
      :model-value="filters.frozen"
      :options="frozenOptions"
      :label="t('finance.transfers.filters.frozenLabel')"
      @update:model-value="emit('filter', 'frozen', $event)"
    />
    <AppSelect
      variant="filter"
      :model-value="sort"
      :options="sortOptions"
      :label="t('finance.filters.sort')"
      @update:model-value="emit('sort', $event)"
    />
  </div>
</template>
