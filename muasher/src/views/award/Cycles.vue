<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import CycleFilters from '@/components/award/CycleFilters.vue'
import CycleTable from '@/components/award/CycleTable.vue'
import { useCycleList } from '@/composables/useCycleList'
import { useCycleAuthority } from '@/composables/useCycleAuthority'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const {
  items,
  status,
  errorMessage,
  load,
  filters,
  paginator,
  searchInput,
  stats,
  setFilter,
  setSort,
} = useCycleList()
const { pagination, setPage } = paginator
const { canCreate } = useCycleAuthority()

useHead({ title: () => t('cycles.list.title') })

const statCards = computed(() => {
  const value = stats.value
  if (!value) return []
  return [
    {
      id: 'total',
      icon: 'layers',
      value: value.total,
      label: t('cycles.stats.total'),
    },
    {
      id: 'open',
      icon: 'door-open',
      value: value.byStatus.submissionOpen ?? 0,
      label: t('cycles.stats.open'),
    },
    {
      id: 'draft',
      icon: 'doc',
      value: value.byStatus.draft ?? 0,
      label: t('cycles.stats.draft'),
    },
    {
      id: 'closed',
      icon: 'docs',
      value: value.byStatus.closed ?? 0,
      label: t('cycles.stats.closed'),
    },
  ]
})
</script>

<template>
  <section class="cycles-page">
    <header class="cycles-page__header">
      <div>
        <p class="cycles-page__eyebrow">{{ t('advanced.eyebrow') }}</p>
        <h1 class="cycles-page__title">{{ t('cycles.list.title') }}</h1>
        <p class="cycles-page__subtitle">{{ t('cycles.list.subtitle') }}</p>
      </div>
      <RouterLink
        v-if="canCreate"
        class="btn btn--primary btn--md"
        :to="{ name: ROUTE_NAMES.AWARD_CYCLE_NEW }"
      >
        <AppIcon name="plus" />
        {{ t('cycles.list.new') }}
      </RouterLink>
    </header>
    <StatsCards :stats="statCards" :loading="!stats && status === 'loading'" />
    <Card>
      <ListToolbar
        v-model:search="searchInput"
        :search-placeholder="t('cycles.list.searchPlaceholder')"
      >
        <template #filters>
          <CycleFilters
            :filters="filters.filters"
            :sort="filters.sort.value"
            @filter="setFilter"
            @sort="setSort"
          />
        </template>
      </ListToolbar>
      <CycleTable :items="items" :status="status" :error-message="errorMessage" @retry="load" />
      <Pagination
        v-if="status === 'success' && pagination.lastPage > 1"
        :page="pagination.page"
        :last-page="pagination.lastPage"
        @update:page="setPage($event)"
      />
    </Card>
  </section>
</template>
