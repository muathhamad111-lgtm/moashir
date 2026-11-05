<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { SCAN_STATUSES } from '@/constants/finance'
import { formatDate } from '@/utils/datetime'

defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
  busy: { type: Object, required: true },
})

const emit = defineEmits(['retry', 'download'])
const { t, locale } = useI18n()

const columns = computed(() => [
  { key: 'payment', label: t('finance.fields.reference') },
  { key: 'amount', label: t('finance.refund.amount') },
  { key: 'refundedOn', label: t('finance.refund.refundedOn') },
  { key: 'transferReference', label: t('finance.refund.transferReference') },
  { key: 'recordedBy', label: t('finance.refund.recordedBy') },
  { key: 'evidence', label: t('finance.refund.evidence.label') },
])

function dayOf(value) {
  return formatDate(`${value}T12:00:00+03:00`, locale.value, {
    dateStyle: 'medium',
    timeZone: 'Asia/Riyadh',
  })
}
</script>

<template>
  <DataTable
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t('finance.refunds.emptyTitle')"
    :empty-message="t('finance.refunds.emptyMessage')"
    @retry="emit('retry')"
  >
    <template #cell-payment="{ item }">
      <RouterLink
        class="transactions-table__link"
        :to="{ name: ROUTE_NAMES.FINANCE_TRANSACTION, params: { id: item.payment.id } }"
      >
        <bdi dir="ltr">{{ item.payment.reference }}</bdi>
      </RouterLink>
    </template>
    <template #cell-amount="{ item }"
      ><MoneyAmount :value="item.amount" :currency="item.currency" strong
    /></template>
    <template #cell-refundedOn="{ item }">{{ dayOf(item.refundedOn) }}</template>
    <template #cell-transferReference="{ item }"
      ><bdi dir="ltr">{{ item.transferReference }}</bdi></template
    >
    <template #cell-recordedBy="{ item }">{{ item.recordedBy.name }}</template>
    <template #cell-evidence="{ item }">
      <span class="refunds-table__evidence">
        <Badge :variant="item.evidence.scanStatus === SCAN_STATUSES.CLEAN ? 'success' : 'danger'">{{
          t(`finance.scan.${item.evidence.scanStatus}`)
        }}</Badge>
        <BaseButton
          variant="ghost"
          size="sm"
          icon="download"
          :disabled="item.evidence.scanStatus !== SCAN_STATUSES.CLEAN"
          :loading="busy[item.id] === true"
          :aria-label="
            t('finance.refund.downloadEvidenceOf', { reference: item.payment.reference })
          "
          @click="emit('download', item)"
        >
          {{ t('finance.refund.downloadEvidence') }}
        </BaseButton>
      </span>
    </template>
  </DataTable>
</template>
