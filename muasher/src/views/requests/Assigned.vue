<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import { RouterLink } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import Tabs from '@/components/ui/Tabs.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import OverdueBadge from '@/components/accredited/OverdueBadge.vue'
import PathBadge from '@/components/ui/PathBadge.vue'
import RequestPathSwitch from '@/components/requests/RequestPathSwitch.vue'
import { useAssignedRequests } from '@/composables/useAssignedRequests'
import { useAuthStore } from '@/stores/auth'
import { useRequestPathCountsStore } from '@/stores/requestPathCounts'
import { assignedPathsOf, pathCountOf } from '@/utils/requestPaths'
import { ASSIGNED_TABS } from '@/constants/accredited'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

const TAB_COUNT_KEYS = { new: 'new', in_progress: 'inProgress', completed: 'completed' }

const { t, locale } = useI18n()
const auth = useAuthStore()
const counts = useRequestPathCountsStore()
const paths = computed(() => assignedPathsOf(auth.user, counts.assigned))
const { items, status, errorMessage, load, tab, path, pathCounts, paginator } =
  useAssignedRequests(paths)
const { pagination, setPage } = paginator

useHead({ title: () => t('requests.assigned.title') })

const current = computed(() => pathCountOf(pathCounts.value, path.value))
const tabs = computed(() =>
  Object.values(ASSIGNED_TABS).map((value) => {
    const label = t(`requests.assigned.tabs.${value}`)
    const count = current.value?.[TAB_COUNT_KEYS[value]]
    return { value, label: count === undefined ? label : `${label} (${count})` }
  }),
)
const switchEntries = computed(() =>
  paths.value.map((value) => {
    const entry = pathCountOf(pathCounts.value, value)
    const open = entry ? entry.new + entry.inProgress : null
    return {
      path: value,
      pathLabel: entry?.pathLabel ?? null,
      countLabel: open === null ? '' : t('nav.pathCounts.assigned', { count: open }),
    }
  }),
)
const columns = computed(() => [
  { key: 'number', label: t('requests.columns.number') },
  { key: 'entity', label: t('requests.columns.entity') },
  { key: 'cycle', label: t('requests.columns.pathAndCycle') },
  { key: 'status', label: t('requests.columns.status') },
  { key: 'assignedAt', label: t('requests.assignment.assignedAt') },
  { key: 'dueAt', label: t('requests.columns.dueAt') },
])
</script>

<template>
  <section class="requests-page">
    <header class="requests-page__header">
      <p class="requests-page__eyebrow">{{ t('requests.index.eyebrow') }}</p>
      <div class="requests-page__title-row">
        <h1 class="requests-page__title">{{ t('requests.assigned.title') }}</h1>
        <PathBadge :path="path" :label="current?.pathLabel ?? null" />
      </div>
      <p class="requests-page__subtitle">{{ t('requests.assigned.subtitle') }}</p>
    </header>
    <RequestPathSwitch
      v-if="paths.length > 1"
      :entries="switchEntries"
      :current="path"
      :route-name="ROUTE_NAMES.REQUESTS_ASSIGNED"
      :label="t('requests.assigned.pathSwitchLabel')"
    />
    <Card>
      <Tabs v-model="tab" :tabs="tabs" />
      <DataTable
        :columns="columns"
        :items="items"
        :status="status"
        :error-message="errorMessage"
        :empty-title="t('requests.assigned.emptyTitle')"
        :empty-message="t('requests.assigned.emptyMessage')"
        @retry="load"
      >
        <template #cell-number="{ item }">
          <RouterLink
            class="request-queue__link"
            :to="{ name: ROUTE_NAMES.REQUESTS_REVIEW, params: { id: item.id } }"
            ><bdi dir="ltr">{{ item.number }}</bdi></RouterLink
          >
        </template>
        <template #cell-entity="{ item }">
          {{ localizedText(item.entity?.name, locale) }}
          <span v-if="item.entity?.region" class="request-queue__muted">{{
            localizedText(item.entity.region.name, locale)
          }}</span>
        </template>
        <template #cell-cycle="{ item }">
          <span class="request-queue__path">
            <PathBadge v-if="item.path" :path="item.path" :label="item.pathLabel" compact />
            <span v-if="item.awardCycle" class="request-queue__muted">{{
              localizedText(item.awardCycle.name, locale)
            }}</span>
          </span>
        </template>
        <template #cell-status="{ item }"><StatusBadge :status="item.status" /></template>
        <template #cell-assignedAt="{ item }"
          ><DateStamp :value="item.assignedAt" :with-time="false"
        /></template>
        <template #cell-dueAt="{ item }">
          <span class="request-queue__due">
            <DateStamp :value="item.completionDeadline ?? item.dueAt" :with-time="false" />
            <OverdueBadge v-if="item.dueAt && !item.completedAt" :overdue="item.isOverdue" />
          </span>
        </template>
      </DataTable>
      <Pagination
        v-if="status === 'success' && pagination.lastPage > 1"
        :page="pagination.page"
        :last-page="pagination.lastPage"
        @update:page="setPage($event)"
      />
    </Card>
  </section>
</template>
