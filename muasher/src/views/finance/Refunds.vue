<script setup>
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import AppSelect from '@/components/form/AppSelect.vue'
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
const filterState = { filters: filters.filters, setFilter }
</script>

<template>
  <section class="finance-page">
    <FinancePageHeader
      :eyebrow="t('finance.eyebrow')"
      :title="t('finance.refunds.title')"
      :subtitle="t('finance.refunds.subtitle')"
    />
    <FinanceNav />
    <Card>
      <ListToolbar :searchable="false" :filter-state="filterState">
        <template #filters="{ draft }">
          <AppSelect
            :model-value="draft.filters.purpose"
            :options="purposeOptions"
            :label="t('finance.filters.service')"
            @update:model-value="draft.setFilter('purpose', $event)"
          />
          <BaseDatePicker
            :model-value="draft.filters.from"
            name="refunds-from"
            :label="t('finance.filters.from')"
            :max="draft.filters.to"
            @update:model-value="draft.setFilter('from', $event)"
          />
          <BaseDatePicker
            :model-value="draft.filters.to"
            name="refunds-to"
            :label="t('finance.filters.to')"
            :min="draft.filters.from"
            @update:model-value="draft.setFilter('to', $event)"
          />
        </template>
      </ListToolbar>
      <RefundsTable
        :items="items"
        :status="status"
        :error-message="errorMessage"
        :busy="downloads.busy"
        @retry="load"
        @download="downloadEvidence"
      />
      <Pagination
        v-if="status === 'success' && pagination.lastPage > 1"
        :page="pagination.page"
        :last-page="pagination.lastPage"
        @update:page="setPage($event)"
      />
    </Card>
  </section>
</template>
