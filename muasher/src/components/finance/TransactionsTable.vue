<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import RowActions from '@/components/data-list/RowActions.vue'
import Badge from '@/components/ui/Badge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import PaymentStatusBadge from '@/components/payments/PaymentStatusBadge.vue'
import PaymentChannelCell from '@/components/payments/portal/PaymentChannelCell.vue'
import Tooltip from '@/components/ui/Tooltip.vue'
import { STATUS_DEFINITION_KEY } from '@/constants/finance'
import { formatRiyadhDate, formatRiyadhTime } from '@/utils/datetime'
import { formatRate } from '@/utils/payments/amountFormat'
import { entityInitials } from '@/utils/finance/entityInitials'
import { transactionColumns } from '@/utils/finance/transactionColumns'
import { TRANSACTION_INLINE_ACTIONS } from '@/utils/finance/transactionRowActions'

defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
  sort: { type: String, default: '' },
  emptyMessage: { type: String, default: '' },
  // The row's actions by its status and the viewer's permissions (useTransactionRowActions).
  actionsFor: { type: Function, required: true },
})

const emit = defineEmits(['retry', 'sort', 'action'])
const { t, locale } = useI18n()

// The viewer picks the optional columns («الأعمدة»); the operation and actions always show.
const columns = computed(() => transactionColumns(t))

function statusDefinition(status) {
  const key = STATUS_DEFINITION_KEY[status] ?? status
  return t(`finance.operations.statuses.${key}.definition`)
}

function localized(name) {
  return name?.[locale.value] || name?.ar || ''
}

function nameOf(entity) {
  return localized(entity.name)
}
</script>

<template>
  <DataTable
    class="transactions-table"
    stacked
    table-key="finance-transactions"
    :columns="columns"
    :items="items"
    :status="status"
    :sort="sort"
    :error-message="errorMessage"
    :empty-title="t('finance.transactions.emptyTitle')"
    :empty-message="emptyMessage || t('finance.transactions.emptyMessage')"
    @retry="emit('retry')"
    @sort="emit('sort', $event)"
  >
    <template v-if="$slots['empty-action']" #empty-action><slot name="empty-action" /></template>
    <template #cell-operation="{ item }">
      <span class="transactions-table__stack">
        <bdi class="transactions-table__reference" dir="ltr">{{ item.reference }}</bdi>
        <span class="transactions-table__muted">{{ t(`finance.purposes.${item.purpose}`) }}</span>
      </span>
    </template>
    <template #cell-entity="{ item }">
      <span class="transactions-table__entity">
        <span class="transactions-table__avatar" aria-hidden="true">{{
          entityInitials(nameOf(item.entity))
        }}</span>
        <span class="transactions-table__stack">
          <span class="transactions-table__title">{{ nameOf(item.entity) }}</span>
          <bdi class="transactions-table__muted" dir="ltr">{{ item.entity.reference }}</bdi>
        </span>
      </span>
    </template>
    <template #cell-total="{ item }">
      <span class="transactions-table__stack">
        <MoneyAmount :value="item.pricing.total" :currency="item.pricing.currency" strong />
        <span class="transactions-table__muted"
          >{{ t('finance.pricing.vatShort') }}
          <MoneyAmount :value="item.pricing.vatAmount" :currency="item.pricing.currency"
        /></span>
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
      <span class="transactions-table__stack">
        <Tooltip :text="statusDefinition(item.status)">
          <template #default="{ describedBy }">
            <span class="transactions-table__status" tabindex="0" :aria-describedby="describedBy">
              <PaymentStatusBadge :status="item.status" />
            </span>
          </template>
        </Tooltip>
        <span
          v-if="item.isTest || item.latePaid || item.manuallySettled"
          class="transactions-table__badges"
        >
          <Badge v-if="item.isTest" variant="warning">{{ t('finance.testMode.badge') }}</Badge>
          <Badge v-if="item.latePaid" variant="danger">{{
            t('finance.transactions.latePaid')
          }}</Badge>
          <Badge v-if="item.manuallySettled" variant="info">{{
            t('finance.transactions.manuallySettled')
          }}</Badge>
        </span>
      </span>
    </template>
    <template #cell-createdAt="{ item }">
      <time class="transactions-table__stack transactions-table__date" :datetime="item.createdAt">
        <span>{{ formatRiyadhDate(item.createdAt, locale) }}</span>
        <span>{{ formatRiyadhTime(item.createdAt, locale) }}</span>
      </time>
    </template>
    <template #cell-receipt="{ item }">
      <bdi v-if="item.receiptNumber" class="transactions-table__nowrap" dir="ltr">{{
        item.receiptNumber
      }}</bdi>
      <span v-else class="transactions-table__muted">{{ t('finance.common.none') }}</span>
    </template>
    <template #cell-beforeVat="{ item }"
      ><MoneyAmount :value="item.pricing.priceBeforeVat" :currency="item.pricing.currency"
    /></template>
    <template #cell-type="{ item }">{{
      item.type ? t(`finance.operations.types.${item.type}.label`) : ''
    }}</template>
    <template #cell-awardCycle="{ item }">{{ localized(item.awardCycle?.name) }}</template>
    <template #cell-paidAt="{ item }"
      ><DateStamp v-if="item.paidAt" :value="item.paidAt" :with-time="false"
    /></template>
    <template #cell-vatAmount="{ item }"
      ><MoneyAmount :value="item.pricing.vatAmount" :currency="item.pricing.currency"
    /></template>
    <template #cell-vatRate="{ item }">{{
      item.pricing.vatRate
        ? t('finance.transactions.columns.vatRateValue', {
            rate: formatRate(item.pricing.vatRate, locale),
          })
        : ''
    }}</template>
    <template #cell-refundAmount="{ item }"
      ><MoneyAmount v-if="item.refund" :value="item.refund.amount" :currency="item.refund.currency"
    /></template>
    <template #cell-refundedAt="{ item }"
      ><DateStamp v-if="item.refundedAt" :value="item.refundedAt" :with-time="false"
    /></template>
    <template #cell-updatedAt="{ item }"
      ><DateStamp :value="item.updatedAt" :with-time="false"
    /></template>
    <template #cell-actions="{ item }">
      <RowActions
        :actions="actionsFor(item)"
        :name="item.reference"
        :inline-keys="TRANSACTION_INLINE_ACTIONS"
        @select="emit('action', { key: $event, item })"
      />
    </template>
  </DataTable>
</template>
