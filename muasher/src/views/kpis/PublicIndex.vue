<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useKpisCatalog } from '@/composables/useKpisCatalog'
import { usePublicPage } from '@/composables/usePublicPage'
import { usePageSeo } from '@/composables/usePageSeo'
import { ALL } from '@/composables/useKpiFilters'
import { KPI_DEFAULT_SORT } from '@/constants/kpis'
import { CMS_PAGE_KEYS } from '@/constants/cms'
import { ROUTE_NAMES } from '@/constants/routeNames'
import KpiCard from '@/components/kpis/KpiCard.vue'
import KpiCatalogFilters from '@/components/kpis/KpiCatalogFilters.vue'
import Tabs from '@/components/ui/Tabs.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { usePlatformIdentity } from '@/composables/usePlatformIdentity'

const { t } = useI18n()
const { platformName } = usePlatformIdentity()
const router = useRouter()
const catalog = useKpisCatalog()
const {
  kpis,
  status,
  loadingMore,
  hasMore,
  filters,
  categories,
  types,
  localized,
  load,
  showMore,
  set,
} = catalog
const pageContent = usePublicPage(CMS_PAGE_KEYS.KPIS)

const hero = computed(() => pageContent.sectionOfType('hero'))
const heroTitle = computed(() => localized(hero.value?.title) || t('kpis.page.title'))
const heroSubtitle = computed(() => localized(hero.value?.description) || t('kpis.page.subtitle'))

usePageSeo({
  title: () => localized(pageContent.page.value?.seo?.title) || localized(hero.value?.title),
  description: () =>
    localized(pageContent.page.value?.seo?.description) || localized(hero.value?.description),
})

const categoryTabs = computed(() => [
  { value: ALL, label: t('kpis.page.tabs.all') },
  ...categories.value.map((category) => ({ value: category.id, label: localized(category.name) })),
])

function goToDetail(kpi) {
  router.push({ name: ROUTE_NAMES.PUBLIC_KPIS_DETAIL, params: { slug: kpi.slug } })
}
const filterState = {
  filters,
  defaults: { status: ALL, type: ALL, year: ALL, owner: '' },
  setFilter: set,
  sort: computed(() => filters.sort),
  setSort: (value) => set('sort', value),
  defaultSort: KPI_DEFAULT_SORT,
}

function setDraft(draft, key, value) {
  if (key === 'sort') draft.setSort(value)
  else draft.setFilter(key, value)
}
</script>

<template>
  <div class="kpis-page">
    <header class="kpis-page__hero">
      <div class="kpis-page__hero-inner">
        <span class="kpis-page__eyebrow">
          <AppIcon name="chart-line" />
          {{ platformName }}
        </span>
        <h1 class="kpis-page__title">{{ heroTitle }}</h1>
        <p class="kpis-page__subtitle">{{ heroSubtitle }}</p>
      </div>
    </header>

    <div class="kpis-page__body">
      <div class="kpis-page__controls">
        <Tabs
          v-if="categories.length"
          :model-value="filters.category"
          :tabs="categoryTabs"
          class="kpis-page__tabs"
          @update:model-value="set('category', $event)"
        />
        <ListToolbar
          :search="filters.search"
          :search-placeholder="t('kpis.page.searchPlaceholder')"
          class="kpis-page__toolbar"
          :filter-state="filterState"
          @update:search="set('search', $event)"
        >
          <template #filters="{ draft }">
            <KpiCatalogFilters
              :filters="{ ...draft.filters, sort: draft.sort.value }"
              :types="types"
              :localized="localized"
              @change="(key, value) => setDraft(draft, key, value)"
            />
          </template>
        </ListToolbar>
      </div>

      <div v-if="status === 'loading'" class="kpis-page__grid" aria-busy="true">
        <Skeleton v-for="i in 9" :key="i" variant="block" />
      </div>

      <ErrorState v-else-if="status === 'error'" :message="t('kpis.error')" @retry="load" />

      <EmptyState
        v-else-if="status === 'empty'"
        :title="t('kpis.empty.title')"
        :message="t('kpis.empty.message')"
        icon="chart-bar"
      />

      <EmptyState
        v-else-if="!kpis.length"
        :title="t('kpis.page.noResults.title')"
        :message="t('kpis.page.noResults.message')"
        icon="search"
      />

      <template v-else>
        <TransitionGroup tag="ul" name="kpi-grid" class="kpis-page__grid">
          <li v-for="kpi in kpis" :key="kpi.id" class="kpis-page__cell">
            <KpiCard :kpi="kpi" :localized="localized" @activate="goToDetail" />
          </li>
        </TransitionGroup>

        <div v-if="hasMore" class="kpis-page__more">
          <BaseButton
            variant="secondary"
            icon="chevron-down"
            :loading="loadingMore"
            @click="showMore"
          >
            {{ t('kpis.page.showMore') }}
          </BaseButton>
        </div>
      </template>
    </div>
  </div>
</template>
