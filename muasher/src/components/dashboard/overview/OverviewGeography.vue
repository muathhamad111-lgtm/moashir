<script setup>
import { computed, ref, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import RegionMap from '@/components/charts/RegionMap.vue'
import OverviewGeographyDetail from './OverviewGeographyDetail.vue'
import { DEFAULT_GEOGRAPHY_METRIC, GEOGRAPHY_METRICS } from '@/constants/dashboardOverview'
import { useOverviewGeography } from '@/composables/useOverviewGeography'

const props = defineProps({
  overview: { type: Object, required: true },
})

const { t } = useI18n()
const metric = ref(DEFAULT_GEOGRAPHY_METRIC)
const chosen = ref('')
const geography = useOverviewGeography(toRef(props, 'overview'), metric)
const metricLabel = computed(() => t(`dashboard.overview.geography.metrics.${metric.value}`))
// The chosen region, or the strongest one until a region is chosen.
const selected = computed(() => chosen.value || geography.ranking.value[0]?.key || '')
const selectedRegion = computed(
  () => geography.regions.value.find((region) => region.key === selected.value) ?? null,
)
const selectedCities = computed(() =>
  geography.cities.value
    .filter((city) => city.regionKey === selected.value)
    .sort((a, b) => b.value - a.value),
)

function select(key) {
  chosen.value = key
}
</script>

<template>
  <div class="overview-geography">
    <div
      class="overview-period__presets overview-geography__metrics"
      role="group"
      :aria-label="t('dashboard.overview.geography.metricsLabel')"
    >
      <button
        v-for="item in GEOGRAPHY_METRICS"
        :key="item.key"
        type="button"
        class="overview-period__preset"
        :class="{ 'is-active': metric === item.key }"
        :aria-pressed="metric === item.key ? 'true' : 'false'"
        :data-metric="item.key"
        @click="metric = item.key"
      >
        {{ t(`dashboard.overview.geography.metrics.${item.key}`) }}
      </button>
    </div>
    <div class="overview-geography__body">
      <RegionMap
        class="overview-geography__map"
        :regions="geography.regions.value"
        :cities="geography.cities.value"
        :selected="selected"
        :caption="t('dashboard.overview.geography.mapCaption', { metric: metricLabel })"
        @select="select"
      />
      <OverviewGeographyDetail
        :legend="geography.legend.value"
        :ranking="geography.ranking.value"
        :region="selectedRegion"
        :cities="selectedCities"
        :selected="selected"
        @select="select"
      />
    </div>
  </div>
</template>
