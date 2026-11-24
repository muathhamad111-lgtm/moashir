<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, RouterLink } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import CycleStatusBadge from '@/components/award/CycleStatusBadge.vue'
import FilteringFilters from '@/components/award/FilteringFilters.vue'
import FilteringTable from '@/components/award/FilteringTable.vue'
import BulkQualifyAction from '@/components/award/BulkQualifyAction.vue'
import { useFilteringQueue } from '@/composables/useFilteringQueue'
import { FILTERING_STATUSES } from '@/constants/advanced'
import { FILTERING_WINDOW, filteringWindowOf } from '@/utils/award/filteringWindow'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'
import { linkedStatus } from '@/utils/award/linkedStatus'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t, locale } = useI18n()
const route = useRoute()
const cycleId = computed(() => Number(route.params.id))
const {
  items,
  status,
  errorMessage,
  load,
  filters,
  paginator,
  searchInput,
  summary,
  cycle,
  setFilter,
  setSort,
} = useFilteringQueue(cycleId, { status: linkedStatus(route.query.status, FILTERING_STATUSES) })
const { pagination, setPage } = paginator

useHead({ title: () => t('filtering.queue.title') })

const statCards = computed(() => {
  const value = summary.value
  if (!value) return []
  return [
    {
      id: 'awaiting',
      icon: 'refresh-dots',
      value: value.counts.awaitingFiltering,
      label: t('filtering.stats.awaiting'),
    },
    {
      id: 'qualified',
      icon: 'check-circle',
      value: value.counts.qualifiedForCommittee,
      label: t('filtering.stats.qualified'),
    },
    {
      id: 'notQualified',
      icon: 'x',
      value: value.counts.notQualified,
      label: t('filtering.stats.notQualified'),
    },
    {
      id: 'below',
      icon: 'arrow-down',
      value: value.belowThreshold,
      label: t('filtering.stats.below'),
    },
  ]
})
const windowState = computed(() => filteringWindowOf(cycle.value?.status))
const filterState = { filters: filters.filters, setFilter, sort: filters.sort, setSort }
</script>

<template>
  <section class="cycles-page">
    <header class="cycles-page__header">
      <div>
        <RouterLink
          class="cycle-editor__back"
          :to="{ name: ROUTE_NAMES.AWARD_FILTERING, query: { list: 1 } }"
        >
          <AppIcon name="arrow-left" />
          {{ t('filtering.queue.back') }}
        </RouterLink>
        <h1 class="cycles-page__title">{{ t('filtering.queue.title') }}</h1>
        <p v-if="cycle" class="cycles-page__subtitle">
          {{ localizedText(cycle.name, locale) }} <bdi class="u-ltr">{{ cycle.year }}</bdi>
          <CycleStatusBadge :status="cycle.status" />
        </p>
      </div>
      <div class="filtering-queue__side">
        <p v-if="summary" class="filtering-threshold" role="note">
          <AppIcon name="gauge" />
          {{ t('filtering.threshold.label', { value: summary.disqualificationScore }) }}
        </p>
        <BulkQualifyAction
          :key="summary?.counts?.awaitingFiltering ?? 0"
          :cycle-id="cycleId"
          :window-open="windowState === FILTERING_WINDOW.OPEN"
          @done="load"
        />
      </div>
    </header>
    <AssessmentNotice
      v-if="windowState !== FILTERING_WINDOW.OPEN"
      variant="info"
      :title="t(`filtering.window.${windowState}.title`)"
      :message="t(`filtering.window.${windowState}.message`)"
    />
    <StatsCards :stats="statCards" :loading="!summary && status === 'loading'" />
    <Card>
      <ListToolbar
        v-model:search="searchInput"
        :search-placeholder="t('filtering.queue.searchPlaceholder')"
        :filter-state="filterState"
      >
        <template #filters="{ draft }">
          <FilteringFilters
            :filters="draft.filters"
            :sort="draft.sort.value"
            :regions="cycle?.regions ?? []"
            @filter="draft.setFilter"
            @sort="draft.setSort"
          />
        </template>
      </ListToolbar>
      <FilteringTable
        :cycle-id="cycleId"
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
