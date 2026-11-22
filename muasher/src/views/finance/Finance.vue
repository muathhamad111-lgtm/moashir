<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import { useRoute, useRouter } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import Tabs from '@/components/ui/Tabs.vue'
import FinancePageHeader from '@/components/finance/FinancePageHeader.vue'
import FinanceOverviewCards from '@/components/finance/FinanceOverviewCards.vue'
import FinanceOperationsPanel from '@/components/finance/FinanceOperationsPanel.vue'
import TransferQueuePanel from '@/components/finance/TransferQueuePanel.vue'
import PaymentEventsPanel from '@/components/finance/PaymentEventsPanel.vue'
import TransactionReceiptModal from '@/components/finance/TransactionReceiptModal.vue'
import { useTransactions } from '@/composables/useTransactions'
import { usePendingTransfersCount } from '@/composables/usePendingTransfersCount'
import { usePermissions } from '@/composables/usePermissions'
import { FINANCE_TABS } from '@/constants/finance'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { accessFor } from '@/constants/routeAccess'
import { formatNumber } from '@/utils/formatNumber'

// «المالية»: the overview cards above three tabs. Each tab keeps its own address, so the old links
// (and the permission guard of each) still work.
const TAB_ROUTES = Object.freeze({
  [FINANCE_TABS.OPERATIONS]: ROUTE_NAMES.FINANCE_TRANSACTIONS,
  [FINANCE_TABS.TRANSFERS]: ROUTE_NAMES.FINANCE_TRANSFERS,
  [FINANCE_TABS.EVENTS]: ROUTE_NAMES.FINANCE_PAYMENT_EVENTS,
})

const props = defineProps({
  tab: {
    type: String,
    required: true,
    validator: (value) => Object.values(FINANCE_TABS).includes(value),
  },
})

const { t, n } = useI18n()
const route = useRoute()
const router = useRouter()
const { canAccess } = usePermissions()

const allowed = Object.fromEntries(
  Object.entries(TAB_ROUTES).map(([tab, name]) => [tab, canAccess(accessFor(name))]),
)
const operations = allowed[FINANCE_TABS.OPERATIONS] ? useTransactions() : null
const pending = usePendingTransfersCount({ enabled: allowed[FINANCE_TABS.TRANSFERS] })
const viewing = ref(null)

useHead({ title: () => t('finance.page.title') })

const tabs = computed(() =>
  Object.values(FINANCE_TABS)
    .filter((tab) => allowed[tab])
    .map((tab) => ({
      value: tab,
      label: t(`finance.page.tabs.${tab}`),
      badge:
        tab === FINANCE_TABS.TRANSFERS && pending.count.value
          ? formatNumber(n, pending.count.value)
          : '',
    })),
)

function openTab(tab) {
  if (tab === props.tab) return
  router.replace({
    name: TAB_ROUTES[tab],
    query: tab === FINANCE_TABS.OPERATIONS ? route.query : {},
  })
  if (tab === FINANCE_TABS.OPERATIONS) operations?.load()
}

function filterOperations({ status, type = '' }) {
  operations.setFilter('type', type)
  operations.setFilter('status', status)
  openTab(FINANCE_TABS.OPERATIONS)
}

// A decision changes the pending count and the figures of the overview cards.
function onDecided() {
  pending.load()
  operations?.load()
}
</script>

<template>
  <section class="finance-page">
    <FinancePageHeader
      :eyebrow="t('finance.eyebrow')"
      :title="t('finance.page.title')"
      :subtitle="t('finance.page.subtitle')"
    />

    <FinanceOverviewCards
      v-if="operations"
      :stats="operations.stats.value"
      :loading="operations.status.value === 'loading' && !operations.stats.value"
      @select="filterOperations"
    />

    <Card class="finance-page__tabs">
      <Tabs :model-value="tab" :tabs="tabs" @update:model-value="openTab">
        <FinanceOperationsPanel
          v-if="tab === FINANCE_TABS.OPERATIONS && operations"
          :operations="operations"
          @view="viewing = $event.id"
        />
        <TransferQueuePanel v-else-if="tab === FINANCE_TABS.TRANSFERS" @decided="onDecided" />
        <PaymentEventsPanel v-else-if="tab === FINANCE_TABS.EVENTS" />
      </Tabs>
    </Card>

    <TransactionReceiptModal
      v-if="viewing"
      :key="viewing"
      :model-value="true"
      :payment-id="viewing"
      @update:model-value="viewing = null"
    />
  </section>
</template>
