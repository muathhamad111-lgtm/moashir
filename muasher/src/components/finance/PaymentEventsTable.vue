<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import Badge from '@/components/ui/Badge.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatDate } from '@/utils/datetime'

defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t, te, locale } = useI18n()

const RESULT_VARIANTS = {
  applied: 'success',
  pending: 'warning',
  ignored: 'muted',
  rejected: 'danger',
  unmatched: 'warning',
  late_payment: 'danger',
}

const columns = computed(() => [
  { key: 'createdAt', label: t('finance.fields.date') },
  { key: 'source', label: t('finance.events.source') },
  { key: 'eventType', label: t('finance.events.type') },
  { key: 'payment', label: t('finance.fields.reference') },
  { key: 'mode', label: t('finance.events.mode') },
  { key: 'result', label: t('finance.events.result') },
  { key: 'providerRef', label: t('finance.events.providerRef') },
])

function dateOf(value) {
  return formatDate(value, locale.value, {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Asia/Riyadh',
  })
}

function typeOf(value) {
  return te(`finance.events.types.${value}`) ? t(`finance.events.types.${value}`) : value
}
</script>

<template>
  <DataTable
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t('finance.events.emptyTitle')"
    @retry="emit('retry')"
  >
    <template #cell-createdAt="{ item }"
      ><span class="transactions-table__date">{{ dateOf(item.createdAt) }}</span></template
    >
    <template #cell-source="{ item }">{{ t(`finance.events.sources.${item.source}`) }}</template>
    <template #cell-eventType="{ item }">
      <span class="payment-events__type">
        {{ typeOf(item.eventType) }}
        <Badge v-if="item.signatureValid === true" variant="success">{{
          t('finance.events.signed')
        }}</Badge>
      </span>
    </template>
    <template #cell-payment="{ item }">
      <RouterLink
        v-if="item.payment"
        class="transactions-table__link"
        :to="{ name: ROUTE_NAMES.FINANCE_TRANSACTION, params: { id: item.payment.id } }"
      >
        <bdi dir="ltr">{{ item.payment.reference }}</bdi>
      </RouterLink>
      <span v-else class="transactions-table__muted">{{ t('finance.events.unmatched') }}</span>
    </template>
    <template #cell-mode="{ item }">{{
      item.mode ? t(`finance.filters.modes.${item.mode}`) : t('finance.common.none')
    }}</template>
    <template #cell-result="{ item }">
      <Badge v-if="item.result" :variant="RESULT_VARIANTS[item.result] ?? 'muted'">{{
        t(`finance.events.results.${item.result}`)
      }}</Badge>
      <span v-if="item.errorCode" class="payment-events__error"
        ><bdi dir="ltr">{{ item.errorCode }}</bdi></span
      >
    </template>
    <template #cell-providerRef="{ item }"
      ><code class="payment-events__ref" dir="ltr">{{
        item.providerRef ?? t('finance.common.none')
      }}</code></template
    >
  </DataTable>
</template>
