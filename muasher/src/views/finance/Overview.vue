<script setup>
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import FinanceNav from '@/components/finance/FinanceNav.vue'
import FinancePageHeader from '@/components/finance/FinancePageHeader.vue'
import FinanceStatsCards from '@/components/finance/FinanceStatsCards.vue'
import PurposeBreakdown from '@/components/finance/PurposeBreakdown.vue'
import { useFinanceSummary } from '@/composables/useFinanceSummary'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const { range, status, summary, errorMessage, load } = useFinanceSummary()

useHead({ title: () => t('finance.overview.title') })
</script>

<template>
  <section class="finance-page">
    <FinancePageHeader
      :eyebrow="t('finance.eyebrow')"
      :title="t('finance.overview.title')"
      :subtitle="t('finance.overview.subtitle')"
    >
      <template #actions>
        <RouterLink class="finance-page__link" :to="{ name: ROUTE_NAMES.FINANCE_TRANSACTIONS }">
          {{ t('finance.overview.allTransactions') }}
        </RouterLink>
      </template>
    </FinancePageHeader>
    <FinanceNav />

    <Card>
      <div class="finance-page__range">
        <BaseDatePicker
          v-model="range.from"
          name="summary-from"
          :label="t('finance.filters.from')"
          :max="range.to"
        />
        <BaseDatePicker
          v-model="range.to"
          name="summary-to"
          :label="t('finance.filters.to')"
          :min="range.from"
        />
      </div>
      <p class="finance-page__note" role="note">{{ t('finance.overview.liveOnly') }}</p>
    </Card>

    <ErrorState v-if="status === 'error'" :message="errorMessage" @retry="load" />
    <template v-else>
      <FinanceStatsCards :stats="summary" :loading="status === 'loading'" />
      <Card v-if="summary">
        <PurposeBreakdown :rows="summary.byPurpose" />
      </Card>
    </template>
  </section>
</template>
