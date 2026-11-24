<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataListCard from '@/components/data-list/DataListCard.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import OverdueBadge from '@/components/accredited/OverdueBadge.vue'
import AssignedRequestProgress from '@/components/requests/AssignedRequestProgress.vue'
import AssignedRequestActions from '@/components/requests/AssignedRequestActions.vue'
import { useAssignedRequests } from '@/composables/useAssignedRequests'
import { useAuthStore } from '@/stores/auth'
import { useRequestPathCountsStore } from '@/stores/requestPathCounts'
import { assignedPathsOf, pathCountOf } from '@/utils/requestPaths'
import { ASSIGNED_TABS } from '@/constants/accredited'
import { localizedText } from '@/utils/localizedText'

const TAB_COUNT_KEYS = { new: 'new', in_progress: 'inProgress', completed: 'completed' }

const { t, locale } = useI18n()
const auth = useAuthStore()
const counts = useRequestPathCountsStore()
const paths = computed(() => assignedPathsOf(auth.user, counts.assigned))
const {
  items,
  status,
  errorMessage,
  load,
  filters,
  sort,
  setFilter,
  setSort,
  path,
  pathCounts,
  paginator,
} = useAssignedRequests(paths)
const { pagination, setPage } = paginator

useHead({ title: () => t('requests.assigned.title') })

const current = computed(() => pathCountOf(pathCounts.value, path.value))
function countOf(key) {
  return current.value?.[key]
}
// All the requests by default; the filter narrows them to one status group, with its count.
const statusOptions = computed(() => {
  const groups = Object.values(ASSIGNED_TABS).map((value) => ({
    value,
    label: t(`requests.assigned.tabs.${value}`),
    count: countOf(TAB_COUNT_KEYS[value]),
  }))
  const total = groups.every((group) => group.count === undefined)
    ? undefined
    : groups.reduce((sum, group) => sum + (group.count ?? 0), 0)
  return [{ value: '', label: t('dataList.filter.all'), count: total }, ...groups].map(
    (option) => ({
      value: option.value,
      label: option.count === undefined ? option.label : `${option.label} (${option.count})`,
    }),
  )
})
const filterState = { filters, setFilter, defaults: { tab: '' } }
const columns = computed(() => [
  { key: 'request', label: t('requests.assigned.columns.request') },
  { key: 'state', label: t('requests.assigned.columns.state'), sortKey: 'status' },
  {
    key: 'assignedAt',
    label: t('requests.assigned.columns.assignedAt'),
    sortKey: 'assigned_at',
    sortFirst: 'desc',
  },
  { key: 'dueAt', label: t('requests.columns.dueAt'), sortKey: 'due_at' },
  { key: 'progress', label: t('requests.assigned.columns.progress') },
  { key: 'actions', label: t('requests.assigned.columns.actions') },
])
</script>

<template>
  <section class="requests-page">
    <header class="requests-page__header">
      <p class="requests-page__eyebrow">{{ t('requests.index.eyebrow') }}</p>
      <h1 class="requests-page__title">{{ t('requests.assigned.title') }}</h1>
      <p class="requests-page__subtitle">{{ t('requests.assigned.subtitle') }}</p>
    </header>
    <DataListCard>
      <template #toolbar>
        <ListToolbar :searchable="false" :filter-state="filterState">
          <template #filters="{ draft }">
            <AppSelect
              :model-value="draft.filters.tab"
              :options="statusOptions"
              :label="t('requests.assigned.filters.status')"
              @update:model-value="draft.setFilter('tab', $event)"
            />
          </template>
        </ListToolbar>
      </template>
      <DataTable
        :columns="columns"
        :sort="sort"
        @sort="setSort"
        :items="items"
        :status="status"
        :error-message="errorMessage"
        :empty-title="t('requests.assigned.emptyTitle')"
        :empty-message="t('requests.assigned.emptyMessage')"
        @retry="load"
      >
        <template #cell-request="{ item }">
          <span class="assigned-request__entity">{{
            localizedText(item.entity?.name, locale)
          }}</span>
          <bdi class="assigned-request__number" dir="ltr">{{ item.number }}</bdi>
          <span v-if="item.entity?.region" class="request-queue__muted">{{
            localizedText(item.entity.region.name, locale)
          }}</span>
        </template>
        <template #cell-state="{ item }">
          <StatusBadge :status="item.status" />
        </template>
        <template #cell-assignedAt="{ item }">
          <DateStamp v-if="item.assignedAt" :value="item.assignedAt" :with-time="false" />
        </template>
        <template #cell-dueAt="{ item }">
          <span class="request-queue__due">
            <DateStamp :value="item.completionDeadline ?? item.dueAt" :with-time="false" />
            <OverdueBadge v-if="item.dueAt && !item.completedAt" :overdue="item.isOverdue" />
          </span>
        </template>
        <template #cell-progress="{ item }">
          <AssignedRequestProgress :progress="item.reviewProgress" />
        </template>
        <template #cell-actions="{ item }">
          <AssignedRequestActions
            :request-id="item.id"
            :request-number="item.number"
            :status="item.status"
          />
        </template>
      </DataTable>
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
