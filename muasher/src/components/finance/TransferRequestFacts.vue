<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatRiyadhDateTime } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'
import { ibanLast4 } from '@/utils/payments/iban'

const props = defineProps({
  transfer: { type: Object, required: true },
})

const { t, locale } = useI18n()

const account = computed(() =>
  t('finance.transfers.fields.accountLine', {
    bank: localizedText(props.transfer.bankAccount.bankName, locale.value),
    last4: ibanLast4(props.transfer.bankAccount.iban),
  }),
)
const rows = computed(() => [
  {
    key: 'entity',
    value: localizedText(props.transfer.entity.name, locale.value),
  },
  { key: 'service', value: t(`finance.purposes.${props.transfer.purpose}`) },
  { key: 'account', value: account.value },
  {
    key: 'submittedAt',
    value: formatRiyadhDateTime(props.transfer.submittedAt, locale.value),
  },
  {
    key: 'mode',
    value: t(`finance.transfers.modes.${props.transfer.isTest ? 'test' : 'live'}`),
  },
])
</script>

<template>
  <dl class="transfer-facts">
    <div v-for="row in rows" :key="row.key" class="transfer-facts__row">
      <dt>{{ t(`finance.transfers.fields.${row.key}`) }}</dt>
      <dd>{{ row.value }}</dd>
    </div>
  </dl>
</template>
