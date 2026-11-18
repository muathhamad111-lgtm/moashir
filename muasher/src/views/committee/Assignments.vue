<script setup>
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import Tabs from '@/components/ui/Tabs.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import CommitteeAssignmentTable from '@/components/committee/CommitteeAssignmentTable.vue'
import { useCommitteeAssignments } from '@/composables/useCommitteeAssignments'
import { useCountedTabs } from '@/composables/useCountedTabs'
import { ASSIGNMENT_FILTERS, ASSIGNMENT_STATUSES } from '@/constants/committee'
import { localizedText } from '@/utils/localizedText'
import { toCanonicalStatus } from '@/utils/canonicalStatus'

const { t, locale } = useI18n()
const { items, status, errorMessage, load, filters, paginator, setFilter, appointments, meta } =
  useCommitteeAssignments()
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
// One tab per status with its count, like the assessor's assigned requests.
const { tabs, tab } = useCountedTabs({
  values: ASSIGNMENT_FILTERS,
  labelOf: (value) => t(`award.states.row.${toCanonicalStatus(value)}`),
  countOf: (value) => meta.value.statusCounts?.[COUNT_KEYS[value]],
  counts: computed(() => meta.value.statusCounts),
  current: () => filters.filters.status,
  select: (value) => setFilter('status', value),
})
const overdue = computed(() => items.value.filter((row) => row.isOverdue).length)
// The status lives in the tabs; the filter drawer only holds the cycle.
const filterState = { filters: filters.filters, setFilter, defaults: { cycleId: '' } }
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
    <Card>
      <Tabs v-model="tab" :tabs="tabs" />
      <ListToolbar :searchable="false" :filter-state="filterState">
        <template #filters="{ draft }">
          <AppSelect
            :model-value="draft.filters.cycleId"
            :options="cycleOptions"
            :label="t('award.work.filters.cycle')"
            @update:model-value="draft.setFilter('cycleId', $event)"
          />
        </template>
      </ListToolbar>
      <CommitteeAssignmentTable
        :items="items"
        :status="status"
        :error-message="errorMessage"
        @retry="load"
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
