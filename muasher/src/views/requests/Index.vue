<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import RequestQueueFilters from '@/components/requests/RequestQueueFilters.vue'
import RequestQueueTable from '@/components/requests/RequestQueueTable.vue'
import { useRequestQueue } from '@/composables/useRequestQueue'
import { usePermissions } from '@/composables/usePermissions'
import { requestsService } from '@/services/requestsService'
import { awardCycleService } from '@/services/awardCycleService'
import { ACCREDITED_STATUSES } from '@/constants/accredited'
import { ADVANCED_STATUSES, CYCLE_STATUSES } from '@/constants/advanced'
import { PERMISSIONS } from '@/constants/permissionKeys'

const AWAITING = [ACCREDITED_STATUSES.SUBMITTED, ADVANCED_STATUSES.AWAITING_ASSIGNMENT]
const IN_PROGRESS = [ACCREDITED_STATUSES.ASSIGNED, ACCREDITED_STATUSES.UNDER_REVIEW, ACCREDITED_STATUSES.NEEDS_COMPLETION, ADVANCED_STATUSES.UNDER_INITIAL_ASSESSMENT]
const CYCLE_LIST_SIZE = 50

const { t } = useI18n()
const { can } = usePermissions()
const { items, status, errorMessage, load, filters, paginator, searchInput, stats, setFilter, setSort } = useRequestQueue()
const { pagination, setPage } = paginator
const assessors = ref([])
const cycles = ref([])

useHead({ title: () => t('requests.index.title') })

const statCards = computed(() => {
  const value = stats.value
  if (!value) return []
  return [
    { id: 'total', icon: 'fa-solid fa-inbox', value: value.total, label: t('requests.stats.total') },
    { id: 'awaiting', icon: 'fa-solid fa-user-clock', value: AWAITING.reduce((sum, key) => sum + (value.byStatus[key] ?? 0), 0), label: t('requests.stats.awaiting') },
    { id: 'progress', icon: 'fa-solid fa-clipboard-check', value: IN_PROGRESS.reduce((sum, key) => sum + (value.byStatus[key] ?? 0), 0), label: t('requests.stats.inProgress') },
    { id: 'overdue', icon: 'fa-solid fa-clock', value: value.overdue, label: t('requests.stats.overdue') },
  ]
})

async function loadAssessors() {
  if (!can(PERMISSIONS.REQUESTS_ASSIGN)) return
  try {
    const { data } = await requestsService.listAssessors()
    assessors.value = data
  } catch {
    assessors.value = []
  }
}

async function loadCycles() {
  if (!can(PERMISSIONS.AWARD_CYCLES_VIEW)) return
  try {
    const { data } = await awardCycleService.list({ perPage: CYCLE_LIST_SIZE })
    cycles.value = data.filter((cycle) => cycle.status !== CYCLE_STATUSES.DRAFT)
  } catch {
    cycles.value = []
  }
}

onMounted(() => Promise.all([loadAssessors(), loadCycles()]))
</script>

<template>
  <section class="requests-page">
    <header class="requests-page__header">
      <p class="requests-page__eyebrow">{{ t('requests.index.eyebrow') }}</p>
      <h1 class="requests-page__title">{{ t('requests.index.title') }}</h1>
      <p class="requests-page__subtitle">{{ t('requests.index.subtitle') }}</p>
    </header>
    <StatsCards :stats="statCards" :loading="!stats && status === 'loading'" />
    <Card>
      <ListToolbar v-model:search="searchInput" :search-placeholder="t('requests.index.searchPlaceholder')">
        <template #filters>
          <RequestQueueFilters :filters="filters.filters" :sort="filters.sort.value" :assessors="assessors" :cycles="cycles" @filter="setFilter" @sort="setSort" />
        </template>
      </ListToolbar>
      <RequestQueueTable :items="items" :status="status" :error-message="errorMessage" @retry="load" />
      <Pagination v-if="status === 'success' && pagination.lastPage > 1" :page="pagination.page" :last-page="pagination.lastPage" @update:page="setPage($event)" />
    </Card>
  </section>
</template>
