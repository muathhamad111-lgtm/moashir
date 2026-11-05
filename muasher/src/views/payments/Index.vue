<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import DropdownList from '@/components/ui/DropdownList.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import PaymentsTable from '@/components/payments/PaymentsTable.vue'
import { useMyPayments } from '@/composables/useMyPayments'
import { PAYMENT_PURPOSE_VALUES, PAYMENT_STATUS_VALUES } from '@/constants/payments'
import { formatAmount } from '@/utils/payments/amountFormat'

const { t, locale } = useI18n()
const { items, status, errorMessage, load, filters, paginator, stats, setFilter } = useMyPayments()
const { pagination, setPage } = paginator

useHead({ title: () => t('finance.mine.title') })

function money(value) {
  return t('finance.money', {
    amount: formatAmount(value ?? '0.00', locale.value),
    currency: t('finance.currency.SAR'),
  })
}

const statCards = computed(() => [
  {
    id: 'count',
    icon: 'fa-solid fa-receipt',
    value: stats.value?.count ?? 0,
    label: t('finance.mine.stats.count'),
  },
  {
    id: 'paid',
    icon: 'fa-solid fa-circle-check',
    value: stats.value?.paidCount ?? 0,
    label: t('finance.mine.stats.paid'),
  },
  {
    id: 'total',
    icon: 'fa-solid fa-sack-dollar',
    value: money(stats.value?.totalPaid),
    label: t('finance.mine.stats.totalPaid'),
  },
  {
    id: 'vat',
    icon: 'fa-solid fa-percent',
    value: money(stats.value?.totalVat),
    label: t('finance.mine.stats.totalVat'),
  },
])

const statusOptions = computed(() => [
  { value: '', label: t('finance.filters.allStatuses') },
  ...PAYMENT_STATUS_VALUES.map((value) => ({ value, label: t(`finance.status.${value}`) })),
])
const purposeOptions = computed(() => [
  { value: '', label: t('finance.filters.allServices') },
  ...PAYMENT_PURPOSE_VALUES.map((value) => ({ value, label: t(`finance.purposes.${value}`) })),
])
</script>

<template>
  <section class="payments-page">
    <header class="payments-page__header">
      <p class="payments-page__eyebrow">{{ t('finance.mine.eyebrow') }}</p>
      <h1 class="payments-page__title">{{ t('finance.mine.title') }}</h1>
      <p class="payments-page__subtitle">{{ t('finance.mine.subtitle') }}</p>
    </header>

    <StatsCards :stats="statCards" :loading="status === 'loading' && !stats" />
    <p class="payments-page__note" role="note">{{ t('finance.mine.liveOnly') }}</p>

    <Card>
      <div class="payments-page__filters">
        <DropdownList
          :model-value="filters.filters.status"
          :options="statusOptions"
          :placeholder="t('finance.filters.status')"
          @update:model-value="setFilter('status', $event)"
        />
        <DropdownList
          :model-value="filters.filters.purpose"
          :options="purposeOptions"
          :placeholder="t('finance.filters.service')"
          @update:model-value="setFilter('purpose', $event)"
        />
      </div>
      <PaymentsTable :items="items" :status="status" :error-message="errorMessage" @retry="load" />
      <Pagination
        v-if="status === 'success' && pagination.lastPage > 1"
        :page="pagination.page"
        :last-page="pagination.lastPage"
        @update:page="setPage($event)"
      />
    </Card>
  </section>
</template>
