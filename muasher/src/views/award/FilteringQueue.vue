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
import { useFilteringQueue } from '@/composables/useFilteringQueue'
import { CYCLE_FILTERING_STATUSES } from '@/constants/advanced'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

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
} = useFilteringQueue(cycleId)
const { pagination, setPage } = paginator

useHead({ title: () => t('filtering.queue.title') })

const statCards = computed(() => {
  const value = summary.value
  if (!value) return []
  return [
    {
      id: 'awaiting',
      icon: 'fa-solid fa-hourglass-half',
      value: value.counts.awaitingFiltering,
      label: t('filtering.stats.awaiting'),
    },
    {
      id: 'qualified',
      icon: 'fa-solid fa-circle-check',
      value: value.counts.qualifiedForCommittee,
      label: t('filtering.stats.qualified'),
    },
    {
      id: 'notQualified',
      icon: 'fa-solid fa-circle-xmark',
      value: value.counts.notQualified,
      label: t('filtering.stats.notQualified'),
    },
    {
      id: 'below',
      icon: 'fa-solid fa-arrow-down',
      value: value.belowThreshold,
      label: t('filtering.stats.below'),
    },
  ]
})
const decisionsClosed = computed(
  () => Boolean(cycle.value) && !CYCLE_FILTERING_STATUSES.includes(cycle.value.status),
)
</script>

<template>
  <section class="cycles-page">
    <header class="cycles-page__header">
      <div>
        <RouterLink class="cycle-editor__back" :to="{ name: ROUTE_NAMES.AWARD_FILTERING }">
          <i class="fa-solid fa-arrow-left-long u-flip-in-rtl" aria-hidden="true"></i>
          {{ t('filtering.queue.back') }}
        </RouterLink>
        <h1 class="cycles-page__title">{{ t('filtering.queue.title') }}</h1>
        <p v-if="cycle" class="cycles-page__subtitle">
          {{ localizedText(cycle.name, locale) }} <bdi class="u-ltr">{{ cycle.year }}</bdi>
          <CycleStatusBadge :status="cycle.status" />
        </p>
      </div>
      <p v-if="summary" class="filtering-threshold" role="note">
        <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
        {{ t('filtering.threshold.label', { value: summary.disqualificationScore }) }}
      </p>
    </header>
    <AssessmentNotice
      v-if="decisionsClosed"
      variant="info"
      :title="t('filtering.window.title')"
      :message="t('filtering.window.message')"
    />
    <StatsCards :stats="statCards" :loading="!summary && status === 'loading'" />
    <Card>
      <ListToolbar
        v-model:search="searchInput"
        :search-placeholder="t('filtering.queue.searchPlaceholder')"
      >
        <template #filters>
          <FilteringFilters
            :filters="filters.filters"
            :sort="filters.sort.value"
            :regions="cycle?.regions ?? []"
            @filter="setFilter"
            @sort="setSort"
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
