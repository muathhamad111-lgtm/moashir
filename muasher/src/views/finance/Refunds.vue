<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import DropdownList from '@/components/ui/DropdownList.vue'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import FinanceNav from '@/components/finance/FinanceNav.vue'
import FinancePageHeader from '@/components/finance/FinancePageHeader.vue'
import RefundsTable from '@/components/finance/RefundsTable.vue'
import { useFinanceList } from '@/composables/useFinanceList'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { financeService } from '@/services/financeService'
import { PAYMENT_PURPOSE_VALUES } from '@/constants/payments'

const { t } = useI18n()
const { items, status, errorMessage, load, filters, paginator, setFilter } = useFinanceList({
  fetcher: (params) => financeService.listRefunds(params),
  initialFilters: { purpose: '', from: '', to: '' },
})
const { pagination, setPage } = paginator
const downloads = useSignedDownload()

useHead({ title: () => t('finance.refunds.title') })

const purposeOptions = computed(() => [
  { value: '', label: t('finance.filters.allServices') },
  ...PAYMENT_PURPOSE_VALUES.map((value) => ({ value, label: t(`finance.purposes.${value}`) })),
])

function downloadEvidence(refund) {
  downloads.download(refund.id, () => financeService.requestEvidenceDownload(refund.id))
}
</script>

<template>
  <section class="finance-page">
    <FinancePageHeader :eyebrow="t('finance.eyebrow')" :title="t('finance.refunds.title')" :subtitle="t('finance.refunds.subtitle')" />
    <FinanceNav />
    <Card>
      <div class="finance-page__range">
        <DropdownList :model-value="filters.filters.purpose" :options="purposeOptions" :placeholder="t('finance.filters.service')" @update:model-value="setFilter('purpose', $event)" />
        <BaseDatePicker :model-value="filters.filters.from" name="refunds-from" :label="t('finance.filters.from')" :max="filters.filters.to" @update:model-value="setFilter('from', $event)" />
        <BaseDatePicker :model-value="filters.filters.to" name="refunds-to" :label="t('finance.filters.to')" :min="filters.filters.from" @update:model-value="setFilter('to', $event)" />
      </div>
      <RefundsTable :items="items" :status="status" :error-message="errorMessage" :busy="downloads.busy" @retry="load" @download="downloadEvidence" />
      <Pagination v-if="status === 'success' && pagination.lastPage > 1" :page="pagination.page" :last-page="pagination.lastPage" @update:page="setPage($event)" />
    </Card>
  </section>
</template>
