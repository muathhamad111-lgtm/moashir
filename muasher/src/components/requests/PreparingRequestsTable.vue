<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import DataTable from '@/components/data-list/DataTable.vue'
import PathBadge from '@/components/ui/PathBadge.vue'
import ProgressMeter from '@/components/ui/ProgressMeter.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import RequestPaymentSummary from '@/components/requests/RequestPaymentSummary.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t, locale } = useI18n()

const columns = computed(() => [
  { key: 'number', label: t('requests.columns.number') },
  { key: 'entity', label: t('requests.columns.entity') },
  { key: 'payment', label: t('requests.awaitingEntity.columns.payment') },
  { key: 'openedAt', label: t('requests.awaitingEntity.columns.openedAt') },
  { key: 'progress', label: t('requests.awaitingEntity.columns.progress') },
  { key: 'lastActivity', label: t('requests.awaitingEntity.columns.lastActivity') },
  { key: 'open', label: t('components.actions.label') },
])
</script>

<template>
  <DataTable
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t('requests.awaitingEntity.emptyTitle')"
    :empty-message="t('requests.awaitingEntity.emptyMessage')"
    @retry="emit('retry')"
  >
    <template #cell-number="{ item }">
      <span class="request-queue__path">
        <bdi dir="ltr" class="preparing-requests__number">{{ item.number }}</bdi>
        <PathBadge v-if="item.path" :path="item.path" :label="item.pathLabel" compact />
      </span>
    </template>
    <template #cell-entity="{ item }">
      <span>{{ localizedText(item.entity?.name, locale) }}</span>
      <bdi dir="ltr" class="request-queue__muted">{{ item.entity?.reference }}</bdi>
    </template>
    <template #cell-payment="{ item }">
      <RequestPaymentSummary v-if="item.payment" :payment="item.payment" />
      <span v-else class="request-queue__muted">{{ t('requests.awaitingEntity.noPayment') }}</span>
    </template>
    <template #cell-openedAt="{ item }"><DateStamp :value="item.openedAt" /></template>
    <template #cell-progress="{ item }">
      <ProgressMeter
        v-if="item.progress"
        class="preparing-requests__progress"
        :label="t('requests.awaitingEntity.progressLabel')"
        :current="item.progress.complete"
        :total="item.progress.total"
      />
    </template>
    <template #cell-lastActivity="{ item }"><DateStamp :value="item.lastActivityAt" /></template>
    <template #cell-open="{ item }">
      <RouterLink
        class="request-queue__link"
        :to="{ name: ROUTE_NAMES.REQUESTS_DETAIL, params: { id: item.id } }"
      >
        {{ t('requests.awaitingEntity.open') }}
        <span class="preparing-requests__context">{{ item.number }}</span>
      </RouterLink>
    </template>
  </DataTable>
</template>
