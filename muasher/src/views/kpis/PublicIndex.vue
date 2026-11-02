<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useKpisCatalog } from '@/composables/useKpisCatalog'
import { KPI_STATUSES } from '@/constants/kpiCategories'
import { KPI_SORTS } from '@/composables/useKpisCatalog'
import KpiCard from '@/components/kpis/KpiCard.vue'
import Tabs from '@/components/ui/Tabs.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DropdownList from '@/components/ui/DropdownList.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'

const { t } = useI18n()
const router = useRouter()

const {
  status,
  load,
  localized,
  category,
  statusFilter,
  search,
  sort,
  availableCategories,
  filtered,
  visible,
  hasMore,
  setCategory,
  setStatus,
  setSearch,
  setSort,
  showMore,
} = useKpisCatalog()

const categoryTabs = computed(() => [
  { value: 'all', label: t('kpis.page.tabs.all') },
  ...availableCategories.value.map((entry) => ({
    value: entry.key,
    label: localized(entry.label),
  })),
])

const statusOptions = computed(() => [
  { value: 'all', label: t('kpis.page.filters.allStatuses') },
  ...KPI_STATUSES.map((entry) => ({ value: entry.key, label: localized(entry.label) })),
])

const sortOptions = computed(() =>
  KPI_SORTS.map((key) => ({ value: key, label: t(`kpis.page.sort.${key}`) })),
)

function onCategory(value) {
  setCategory(value)
}

function goToDetail(kpi) {
  const target = { name: 'public.kpis.detail', params: { slug: kpi.slug } }
  if (router.hasRoute('public.kpis.detail')) {
    router.push(target)
  }
}
</script>

<template>
  <div class="kpis-page">
    <header class="kpis-page__hero">
      <div class="kpis-page__hero-inner">
        <span class="kpis-page__eyebrow">
          <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
          {{ $t('app.name') }}
        </span>
        <h1 class="kpis-page__title">{{ $t('kpis.page.title') }}</h1>
        <p class="kpis-page__subtitle">{{ $t('kpis.page.subtitle') }}</p>
      </div>
    </header>

    <div class="kpis-page__body">
      <div class="kpis-page__controls">
        <Tabs
          v-if="status === 'success'"
          :model-value="category"
          :tabs="categoryTabs"
          class="kpis-page__tabs"
          @update:model-value="onCategory"
        />

        <ListToolbar
          v-if="status === 'success'"
          :search="search"
          :search-placeholder="$t('kpis.page.searchPlaceholder')"
          class="kpis-page__toolbar"
          @update:search="setSearch"
        >
          <template #filters>
            <label class="kpis-page__filter">
              <span class="kpis-page__filter-label">{{ $t('kpis.page.filters.status') }}</span>
              <DropdownList
                :model-value="statusFilter"
                :options="statusOptions"
                @update:model-value="setStatus"
              />
            </label>
            <label class="kpis-page__filter">
              <span class="kpis-page__filter-label">{{ $t('kpis.page.sort.label') }}</span>
              <DropdownList :model-value="sort" :options="sortOptions" @update:model-value="setSort" />
            </label>
          </template>
        </ListToolbar>
      </div>

      <div v-if="status === 'loading'" class="kpis-page__grid" aria-hidden="true">
        <Skeleton v-for="i in 9" :key="i" variant="block" />
      </div>

      <ErrorState v-else-if="status === 'error'" :message="$t('kpis.error')" @retry="load" />

      <EmptyState
        v-else-if="status === 'empty'"
        :title="$t('kpis.empty.title')"
        :message="$t('kpis.empty.message')"
        icon="fa-solid fa-chart-column"
      />

      <template v-else>
        <EmptyState
          v-if="!filtered.length"
          :title="$t('kpis.page.noResults.title')"
          :message="$t('kpis.page.noResults.message')"
          icon="fa-solid fa-magnifying-glass"
        />

        <template v-else>
          <TransitionGroup tag="ul" name="kpi-grid" class="kpis-page__grid">
            <li v-for="kpi in visible" :key="kpi.id" class="kpis-page__cell">
              <KpiCard :kpi="kpi" :localized="localized" @activate="goToDetail" />
            </li>
          </TransitionGroup>

          <div v-if="hasMore" class="kpis-page__more">
            <BaseButton variant="secondary" icon="fa-solid fa-chevron-down" @click="showMore">
              {{ $t('kpis.page.showMore') }}
            </BaseButton>
          </div>
        </template>
      </template>
    </div>
  </div>
</template>
