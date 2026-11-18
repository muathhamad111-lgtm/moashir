<script setup>
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import TrendLineChart from '@/components/charts/TrendLineChart.vue'
import ColumnChart from '@/components/charts/ColumnChart.vue'
import DonutChart from '@/components/charts/DonutChart.vue'
import GaugeChart from '@/components/charts/GaugeChart.vue'
import SegmentedBar from '@/components/charts/SegmentedBar.vue'
import OverviewPanel from './OverviewPanel.vue'
import { useAssessorOverviewCharts } from '@/composables/useAssessorOverviewCharts'
import { useOverviewFormats } from '@/composables/useOverviewFormats'

const props = defineProps({
  overview: { type: Object, required: true },
  liveCards: { type: Array, required: true },
})

const { t } = useI18n()
const charts = useAssessorOverviewCharts(toRef(props, 'overview'), toRef(props, 'liveCards'))
const format = useOverviewFormats()
const average = computed(
  () => props.overview.cards.find((card) => card.key === 'average_result_percent') ?? null,
)
const averageNote = computed(() =>
  average.value?.previous === null || average.value?.previous === undefined
    ? ''
    : t('dashboard.overview.previousText', { value: format.percent(average.value.previous) }),
)
const inPeriod = t('dashboard.overview.notes.period')
const now = t('dashboard.overview.notes.snapshot')
const T = (key) => t(`dashboard.assessorOverview.charts.${key}`)
const C = (key) => t(`dashboard.assessorOverview.categories.${key}`)
</script>

<template>
  <div class="overview-charts">
    <OverviewPanel
      data-panel="completed"
      :title="T('completed')"
      icon="chart-line"
      :note="inPeriod"
      wide
    >
      <TrendLineChart
        :categories="charts.categories.value"
        :series="charts.completed.value"
        :caption="T('completed')"
        :category-label="t('dashboard.overview.categories.date')"
        :format-value="format.count"
        area
      />
    </OverviewPanel>
    <OverviewPanel data-panel="open" :title="T('openByStatus')" icon="inbox" :note="now">
      <DonutChart
        :segments="charts.openStates.value"
        :caption="T('openByStatus')"
        :category-label="C('status')"
        :value-label="C('reviews')"
        :total-label="C('reviews')"
        :format-value="format.count"
        :format-share="format.share"
      />
    </OverviewPanel>
    <OverviewPanel data-panel="paths" :title="T('byPath')" icon="route" :note="inPeriod">
      <ColumnChart
        :categories="charts.paths.value.categories"
        :series="charts.paths.value.series"
        :caption="T('byPath')"
        :category-label="t('dashboard.overview.categories.path')"
        :format-value="format.count"
        label-all
      />
    </OverviewPanel>
    <OverviewPanel data-panel="average" :title="T('averageResult')" icon="gauge" :note="inPeriod">
      <GaugeChart
        :value="average?.value ?? null"
        :caption="T('averageResult')"
        :value-text="format.percent(average?.value)"
        :note="averageNote"
      />
    </OverviewPanel>
    <OverviewPanel data-panel="levels" :title="T('levels')" icon="award" :note="inPeriod">
      <SegmentedBar
        :segments="charts.levels.value"
        :caption="T('levels')"
        :format-value="format.count"
        :format-share="format.share"
      />
    </OverviewPanel>
  </div>
</template>
