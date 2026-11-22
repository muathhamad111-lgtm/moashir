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
import { STATUS_DEFINITION_KEY } from '@/constants/finance'
import { formatRiyadhDate, formatRiyadhTime } from '@/utils/datetime'
import { entityInitials } from '@/utils/finance/entityInitials'
import { transactionColumns } from '@/utils/finance/transactionColumns'

const props = defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
  sort: { type: String, default: '' },
  isVisible: { type: Function, default: () => true },
  emptyMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry', 'sort', 'view'])
const { t, locale } = useI18n()

// The labels the column chooser lists too; the operation and actions columns are always shown.
const columns = computed(() =>
  transactionColumns(t).filter((column) => props.isVisible(column.key)),
)

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
      <Tooltip :text="statusDefinition(item.status)">
        <template #default="{ describedBy }">
          <span class="transactions-table__status" tabindex="0" :aria-describedby="describedBy">
            <PaymentStatusBadge :status="item.status" />
          </span>
        </template>
      </Tooltip>
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
    <template #cell-actions="{ item }">
      <Tooltip :text="t('finance.transactions.view')">
        <template #default="{ describedBy }">
          <BaseButton
            variant="secondary"
            size="sm"
            icon="eye"
            icon-only
            class="transactions-table__view"
            :aria-label="`${t('finance.transactions.view')} — ${item.reference}`"
            :aria-describedby="describedBy"
            @click="emit('view', item)"
          />
        </template>
      </Tooltip>
    </template>
  </DataTable>
</template>
