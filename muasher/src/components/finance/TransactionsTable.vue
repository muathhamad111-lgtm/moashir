<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import Badge from '@/components/ui/Badge.vue'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import PaymentStatusBadge from '@/components/payments/PaymentStatusBadge.vue'
import PaymentChannelCell from '@/components/payments/portal/PaymentChannelCell.vue'
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
  { key: 'reference', label: t('finance.fields.reference') },
  { key: 'receiptNumber', label: t('finance.fields.receiptNumber') },
  { key: 'purpose', label: t('finance.fields.service') },
  { key: 'entity', label: t('finance.fields.entity') },
  { key: 'channel', label: t('finance.fields.channel') },
  { key: 'method', label: t('finance.fields.method') },
  { key: 'status', label: t('finance.fields.status') },
  { key: 'beforeVat', label: t('finance.pricing.beforeVatShort') },
  { key: 'vat', label: t('finance.pricing.vatShort') },
  { key: 'total', label: t('finance.pricing.total') },
  { key: 'updatedAt', label: t('finance.fields.updatedAt') },
  { key: 'actions', label: t('components.actions.label') },
])

function nameOf(entity) {
  return entity.name?.[locale.value] || entity.name?.ar || ''
}

function dateOf(value) {
  return formatDate(value, locale.value, {
    dateStyle: 'medium',
    timeStyle: 'short',
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
    :empty-title="t('finance.transactions.emptyTitle')"
    :empty-message="t('finance.transactions.emptyMessage')"
    @retry="emit('retry')"
  >
    <template #cell-reference="{ item }">
      <span class="transactions-table__reference">
        <bdi dir="ltr">{{ item.reference }}</bdi>
        <Badge v-if="item.isTest" variant="warning">{{ t('finance.testMode.badge') }}</Badge>
        <Badge v-if="item.latePaid" variant="danger">{{
          t('finance.transactions.latePaid')
        }}</Badge>
        <Badge v-if="item.manuallySettled" variant="info">{{
          t('finance.transactions.manuallySettled')
        }}</Badge>
      </span>
    </template>
    <template #cell-receiptNumber="{ item }">
      <bdi v-if="item.receiptNumber" class="transactions-table__nowrap" dir="ltr">{{
        item.receiptNumber
      }}</bdi>
      <span v-else class="transactions-table__muted">{{ t('finance.common.none') }}</span>
    </template>
    <template #cell-purpose="{ item }">{{ t(`finance.purposes.${item.purpose}`) }}</template>
    <template #cell-entity="{ item }">
      <span class="transactions-table__entity">
        <span class="transactions-table__entity-name">{{ nameOf(item.entity) }}</span>
        <bdi class="transactions-table__entity-email" dir="ltr">{{ item.entity.email }}</bdi>
      </span>
    </template>
    <template #cell-channel="{ item }"
      ><PaymentChannelCell :payment="item" :with-method="false"
    /></template>
    <template #cell-method="{ item }">{{
      item.method ? t(`finance.methods.${item.method}`) : t('finance.common.none')
    }}</template>
    <template #cell-status="{ item }"><PaymentStatusBadge :status="item.status" /></template>
    <template #cell-beforeVat="{ item }"
      ><MoneyAmount :value="item.pricing.priceBeforeVat" :currency="item.pricing.currency"
    /></template>
    <template #cell-vat="{ item }"
      ><MoneyAmount :value="item.pricing.vatAmount" :currency="item.pricing.currency"
    /></template>
    <template #cell-total="{ item }"
      ><MoneyAmount :value="item.pricing.total" :currency="item.pricing.currency" strong
    /></template>
    <template #cell-updatedAt="{ item }"
      ><span class="transactions-table__date">{{ dateOf(item.updatedAt) }}</span></template
    >
    <template #cell-actions="{ item }">
      <RouterLink
        class="transactions-table__link"
        :to="{ name: ROUTE_NAMES.FINANCE_TRANSACTION, params: { id: item.id } }"
      >
        {{ t('finance.transactions.details') }}
        <span class="transactions-table__link-context">{{ item.reference }}</span>
      </RouterLink>
    </template>
  </DataTable>
</template>
