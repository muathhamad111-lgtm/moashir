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
import { useAssignedRequests } from '@/composables/useAssignedRequests'
import { ASSIGNED_TABS } from '@/constants/accredited'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

const { t, locale } = useI18n()
const { items, status, errorMessage, load, tab, paginator } = useAssignedRequests()
const { pagination, setPage } = paginator

useHead({ title: () => t('requests.assigned.title') })

const tabs = computed(() => Object.values(ASSIGNED_TABS).map((value) => ({ value, label: t(`requests.assigned.tabs.${value}`) })))
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
      <h1 class="requests-page__title">{{ t('requests.assigned.title') }}</h1>
      <p class="requests-page__subtitle">{{ t('requests.assigned.subtitle') }}</p>
    </header>
    <Card>
      <Tabs v-model="tab" :tabs="tabs" />
      <DataTable :columns="columns" :items="items" :status="status" :error-message="errorMessage" :empty-title="t('requests.assigned.emptyTitle')" :empty-message="t('requests.assigned.emptyMessage')" @retry="load">
        <template #cell-number="{ item }">
          <RouterLink class="request-queue__link" :to="{ name: ROUTE_NAMES.REQUESTS_REVIEW, params: { id: item.id } }"><bdi dir="ltr">{{ item.number }}</bdi></RouterLink>
        </template>
        <template #cell-entity="{ item }">
          {{ localizedText(item.entity?.name, locale) }}
          <span v-if="item.entity?.region" class="request-queue__muted">{{ localizedText(item.entity.region.name, locale) }}</span>
        </template>
        <template #cell-cycle="{ item }">{{ item.awardCycle ? localizedText(item.awardCycle.name, locale) : t(`requests.paths.${item.path}`) }}</template>
        <template #cell-status="{ item }"><StatusBadge :status="item.status" /></template>
        <template #cell-assignedAt="{ item }"><DateStamp :value="item.assignedAt" :with-time="false" /></template>
        <template #cell-dueAt="{ item }">
          <span class="request-queue__due">
            <DateStamp :value="item.completionDeadline ?? item.dueAt" :with-time="false" />
            <OverdueBadge v-if="item.dueAt && !item.completedAt" :overdue="item.isOverdue" />
          </span>
        </template>
      </DataTable>
      <Pagination v-if="status === 'success' && pagination.lastPage > 1" :page="pagination.page" :last-page="pagination.lastPage" @update:page="setPage($event)" />
    </Card>
  </section>
</template>
