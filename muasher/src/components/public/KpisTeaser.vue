<script setup>
import { computed } from 'vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { useKpis } from '@/composables/useKpis'
import KpiCard from '@/components/kpis/KpiCard.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'

const { kpis, status, load, localized } = useKpis()

const featured = computed(() => kpis.value.slice(0, 4))
</script>

<template>
  <section class="kpis-teaser" aria-labelledby="kpis-teaser-title">
    <header class="section-heading">
      <h2 id="kpis-teaser-title" class="section-heading__title">
        {{ $t('public.kpisTeaser.title') }}
      </h2>
      <p class="section-heading__subtitle">{{ $t('public.kpisTeaser.subtitle') }}</p>
    </header>

    <div v-if="status === 'loading'" class="kpis-teaser__grid">
      <Skeleton v-for="i in 4" :key="i" variant="block" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="$t('kpis.error')" @retry="load" />

    <EmptyState
      v-else-if="status === 'empty'"
      :title="$t('kpis.empty.title')"
      :message="$t('kpis.empty.message')"
      icon="fa-solid fa-chart-column"
    />

    <ul v-else class="kpis-teaser__grid">
      <li v-for="kpi in featured" :key="kpi.id">
        <KpiCard :kpi="kpi" :localized="localized" />
      </li>
    </ul>

    <div class="kpis-teaser__more">
      <router-link class="kpis-teaser__link" :to="{ name: ROUTE_NAMES.PUBLIC_KPIS }">
        {{ $t('public.kpisTeaser.viewAll') }}
        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
      </router-link>
    </div>
  </section>
</template>
