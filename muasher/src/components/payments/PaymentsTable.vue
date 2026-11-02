<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import Badge from '@/components/ui/Badge.vue'
import MoneyAmount from './MoneyAmount.vue'
import PaymentStatusBadge from './PaymentStatusBadge.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatDate } from '@/utils/datetime'

defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t, locale } = useI18n()

const columns = computed(() => [
  { key: 'receipt', label: t('finance.fields.receiptNumber') },
  { key: 'reference', label: t('finance.fields.reference') },
  { key: 'createdAt', label: t('finance.fields.date') },
  { key: 'purpose', label: t('finance.fields.service') },
  { key: 'beforeVat', label: t('finance.pricing.beforeVatShort') },
  { key: 'vat', label: t('finance.pricing.vatShort') },
  { key: 'total', label: t('finance.pricing.total') },
  { key: 'status', label: t('finance.fields.status') },
  { key: 'actions', label: t('components.actions.label') },
])

function dateOf(value) {
  return formatDate(value, locale.value, { dateStyle: 'medium', timeZone: 'Asia/Riyadh' })
}
</script>

<template>
  <DataTable
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t('finance.mine.emptyTitle')"
    :empty-message="t('finance.mine.emptyMessage')"
    @retry="emit('retry')"
  >
    <template #cell-receipt="{ item }">
      <span class="payments-table__receipt">
        <bdi v-if="item.receipt?.number" class="payments-table__nowrap" dir="ltr">{{ item.receipt.number }}</bdi>
        <Badge v-else-if="item.receipt" variant="warning">{{ t('finance.receipt.testBadge') }}</Badge>
        <span v-else class="payments-table__muted">{{ t('finance.common.none') }}</span>
      </span>
    </template>
    <template #cell-reference="{ item }"><bdi class="payments-table__nowrap" dir="ltr">{{ item.reference }}</bdi></template>
    <template #cell-createdAt="{ item }"><span class="payments-table__date">{{ dateOf(item.createdAt) }}</span></template>
    <template #cell-purpose="{ item }">
      <span class="payments-table__service">
        {{ t(`finance.purposes.${item.purpose}`) }}
        <Badge v-if="item.isTest" variant="warning">{{ t('finance.testMode.badge') }}</Badge>
      </span>
    </template>
    <template #cell-beforeVat="{ item }"><MoneyAmount :value="item.pricing.priceBeforeVat" :currency="item.pricing.currency" /></template>
    <template #cell-vat="{ item }"><MoneyAmount :value="item.pricing.vatAmount" :currency="item.pricing.currency" /></template>
    <template #cell-total="{ item }"><MoneyAmount :value="item.pricing.total" :currency="item.pricing.currency" strong /></template>
    <template #cell-status="{ item }"><PaymentStatusBadge :status="item.status" /></template>
    <template #cell-actions="{ item }">
      <RouterLink class="payments-table__link" :to="{ name: ROUTE_NAMES.PAYMENTS_DETAIL, params: { id: item.id } }">
        {{ t('finance.mine.details') }}
        <span class="payments-table__link-context">{{ item.reference }}</span>
      </RouterLink>
    </template>
  </DataTable>
</template>
