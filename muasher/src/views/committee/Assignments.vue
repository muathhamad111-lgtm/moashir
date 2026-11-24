<script setup>
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataListCard from '@/components/data-list/DataListCard.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Pagination from '@/components/data-list/Pagination.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import CommitteeAssignmentTable from '@/components/committee/CommitteeAssignmentTable.vue'
import { useCommitteeAssignments } from '@/composables/useCommitteeAssignments'
import { ASSIGNMENT_FILTERS, ASSIGNMENT_STATUSES } from '@/constants/committee'
import { localizedText } from '@/utils/localizedText'
import { toCanonicalStatus } from '@/utils/canonicalStatus'

const { t, locale } = useI18n()
const {
  items,
  status,
  errorMessage,
  load,
  filters,
  paginator,
  setFilter,
  setSort,
  appointments,
  meta,
} = useCommitteeAssignments()
const { pagination, setPage } = paginator

useHead({ title: () => t('award.work.title') })

const cycleOptions = computed(() => [
  { value: '', label: t('dataList.filter.all') },
  ...appointments.value.map((appointment) => ({
    value: String(appointment.awardCycleId),
    label: localizedText(appointment.cycleName, locale.value),
  })),
])
const COUNT_KEYS = {
  [ASSIGNMENT_STATUSES.NOT_STARTED]: 'notStarted',
  [ASSIGNMENT_STATUSES.IN_PROGRESS]: 'inProgress',
  [ASSIGNMENT_STATUSES.COMPLETED]: 'completed',
}
function counted(label, count) {
  return count === undefined ? label : `${label} (${count})`
}
// All the assignments by default; the filter narrows them to one status, with its count.
const statusOptions = computed(() => {
  const counts = meta.value.statusCounts
  const total = counts ? Object.values(counts).reduce((sum, count) => sum + count, 0) : undefined
  return [
    { value: '', label: counted(t('dataList.filter.all'), total) },
    ...ASSIGNMENT_FILTERS.map((value) => ({
      value,
      label: counted(
        t(`award.states.row.${toCanonicalStatus(value)}`),
        counts?.[COUNT_KEYS[value]],
      ),
    })),
  ]
})
const overdue = computed(() => items.value.filter((row) => row.isOverdue).length)
const filterState = { filters: filters.filters, setFilter, defaults: { cycleId: '', status: '' } }
</script>

<template>
  <section class="cycles-page committee-work">
    <header class="cycles-page__header">
      <div>
        <p class="cycles-page__eyebrow">{{ t('award.work.eyebrow') }}</p>
        <h1 class="cycles-page__title">{{ t('award.work.title') }}</h1>
        <p class="cycles-page__subtitle">{{ t('award.work.subtitle') }}</p>
      </div>
    </header>
    <AssessmentNotice
      v-if="overdue"
      variant="warning"
      :title="t('award.work.overdueTitle', { count: overdue }, overdue)"
      :message="t('award.work.overdueMessage')"
    />
    <DataListCard>
      <template #toolbar>
        <ListToolbar :searchable="false" :filter-state="filterState">
          <template #filters="{ draft }">
            <AppSelect
              :model-value="draft.filters.status"
              :options="statusOptions"
              :label="t('award.work.filters.status')"
              @update:model-value="draft.setFilter('status', $event)"
            />
            <AppSelect
              :model-value="draft.filters.cycleId"
              :options="cycleOptions"
              :label="t('award.work.filters.cycle')"
              @update:model-value="draft.setFilter('cycleId', $event)"
            />
          </template>
        </ListToolbar>
      </template>
      <CommitteeAssignmentTable
        :items="items"
        :status="status"
        :error-message="errorMessage"
        :sort="filters.sort.value"
        @sort="setSort"
        @retry="load"
      />
      <template #footer>
        <Pagination
          v-if="status === 'success' && pagination.lastPage > 1"
          :page="pagination.page"
          :last-page="pagination.lastPage"
          @update:page="setPage($event)"
        />
      </template>
    </DataListCard>
  </section>
</template>
