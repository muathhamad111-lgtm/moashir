<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Badge from '@/components/ui/Badge.vue'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import PaymentStatusBadge from '@/components/payments/PaymentStatusBadge.vue'
import PaymentChannelCell from '@/components/payments/portal/PaymentChannelCell.vue'
import Tooltip from '@/components/ui/Tooltip.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { STATUS_DEFINITION_KEY } from '@/constants/finance'
import { formatRiyadhDate, formatRiyadhTime } from '@/utils/datetime'

defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t, locale } = useI18n()

// The service, payment reference and receipt share one column; the channel and method another.
const columns = computed(() => [
  { key: 'operation', label: t('finance.transactions.columns.operation') },
  { key: 'entity', label: t('finance.fields.entity') },
  { key: 'payment', label: t('finance.transactions.columns.payment') },
  { key: 'status', label: t('finance.fields.status') },
  { key: 'beforeVat', label: t('finance.pricing.beforeVatShort') },
  { key: 'vat', label: t('finance.pricing.vatShort') },
  { key: 'total', label: t('finance.pricing.total') },
  { key: 'updatedAt', label: t('finance.fields.updatedAt') },
  { key: 'actions', label: t('components.actions.label') },
])

// Hovering or focusing a status shows what it means.
function statusDefinition(status) {
  const key = STATUS_DEFINITION_KEY[status] ?? status
  return t(`finance.operations.statuses.${key}.definition`)
}

function nameOf(entity) {
  return entity.name?.[locale.value] || entity.name?.ar || ''
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
    <template #cell-operation="{ item }">
      <span class="transactions-table__stack">
        <span class="transactions-table__title">{{ t(`finance.purposes.${item.purpose}`) }}</span>
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
        <i18n-t
          v-if="item.receiptNumber"
          keypath="finance.transactions.receiptLine"
          scope="global"
          tag="span"
          class="transactions-table__muted"
        >
          <template #number
            ><bdi class="transactions-table__nowrap" dir="ltr">{{
              item.receiptNumber
            }}</bdi></template
          >
        </i18n-t>
      </span>
    </template>
    <template #cell-entity="{ item }">
      <span class="transactions-table__stack">
        <span class="transactions-table__title">{{ nameOf(item.entity) }}</span>
        <bdi class="transactions-table__muted" dir="ltr">{{ item.entity.email }}</bdi>
      </span>
    </template>
    <template #cell-payment="{ item }">
      <span class="transactions-table__stack">
        <PaymentChannelCell :payment="item" :with-method="false" />
        <span v-if="item.method" class="transactions-table__muted">{{
          t(`finance.methods.${item.method}`)
        }}</span>
      </span>
    </template>
    <template #cell-status="{ item }">
      <Tooltip :text="statusDefinition(item.status)">
        <template #default="{ describedBy }">
          <span class="transactions-table__status" tabindex="0" :aria-describedby="describedBy">
            <PaymentStatusBadge :status="item.status" />
          </span>
        </template>
      </Tooltip>
    </template>
    <template #cell-beforeVat="{ item }"
      ><MoneyAmount :value="item.pricing.priceBeforeVat" :currency="item.pricing.currency"
    /></template>
    <template #cell-vat="{ item }"
      ><MoneyAmount :value="item.pricing.vatAmount" :currency="item.pricing.currency"
    /></template>
    <template #cell-total="{ item }"
      ><MoneyAmount :value="item.pricing.total" :currency="item.pricing.currency" strong
    /></template>
    <template #cell-updatedAt="{ item }">
      <time class="transactions-table__stack transactions-table__date" :datetime="item.updatedAt">
        <span>{{ formatRiyadhDate(item.updatedAt, locale) }}</span>
        <span>{{ formatRiyadhTime(item.updatedAt, locale) }}</span>
      </time>
    </template>
    <template #cell-actions="{ item }">
      <Tooltip :text="t('finance.transactions.view')">
        <template #default="{ describedBy }">
          <BaseButton
            variant="secondary"
            size="sm"
            icon="eye"
            icon-only
            class="transactions-table__view"
            :to="{ name: ROUTE_NAMES.FINANCE_TRANSACTION, params: { id: item.id } }"
            :aria-label="`${t('finance.transactions.view')} — ${item.reference}`"
            :aria-describedby="describedBy"
          />
        </template>
      </Tooltip>
    </template>
  </DataTable>
</template>
