<script setup>
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import TrendLineChart from '@/components/charts/TrendLineChart.vue'
import ColumnChart from '@/components/charts/ColumnChart.vue'
import DonutChart from '@/components/charts/DonutChart.vue'
import GaugeChart from '@/components/charts/GaugeChart.vue'
import SegmentedBar from '@/components/charts/SegmentedBar.vue'
import ProgressList from '@/components/charts/ProgressList.vue'
import WidgetCriteria from '../widgets/WidgetCriteria.vue'
import OverviewPanel from './OverviewPanel.vue'
import { useOverviewCharts } from '@/composables/useOverviewCharts'
import { useOverviewFormats } from '@/composables/useOverviewFormats'

const props = defineProps({
  overview: { type: Object, required: true },
})

const { t } = useI18n()
const charts = useOverviewCharts(toRef(props, 'overview'))
const format = useOverviewFormats()
const average = computed(
  () => props.overview.cards.find((card) => card.key === 'accredited_average_percent') ?? null,
)
const averageNote = computed(() =>
  average.value?.previous !== null && average.value?.previous !== undefined
    ? t('dashboard.overview.previousText', { value: format.percent(average.value.previous) })
    : '',
)
const inPeriod = t('dashboard.overview.notes.period')
const now = t('dashboard.overview.notes.snapshot')
const T = (key) => t(`dashboard.overview.charts.${key}`)
const W = (key) => t(`dashboard.widgets.titles.${key}`)
</script>

<template>
  <div class="overview-charts">
    <OverviewPanel
      data-panel="activity"
      :title="T('activity')"
      icon="chart-line"
      :note="inPeriod"
      wide
    >
      <TrendLineChart
        :categories="charts.categories.value"
        :series="charts.activity.value"
        :initial-shown="[charts.activity.value[0].key]"
        :caption="T('activity')"
        :category-label="t('dashboard.overview.categories.date')"
        :format-value="format.count"
      />
    </OverviewPanel>
    <OverviewPanel data-panel="levels" :title="T('levels')" icon="award" :note="now">
      <DonutChart
        :segments="charts.levels.value"
        :caption="T('levels')"
        :category-label="t('dashboard.overview.categories.level')"
        :value-label="t('dashboard.overview.categories.entities')"
        :total-label="t('dashboard.overview.categories.entities')"
        :format-value="format.count"
        :format-share="format.share"
      />
    </OverviewPanel>

    <OverviewPanel data-panel="revenue" :title="T('revenue')" icon="sar" :note="inPeriod" wide>
      <ColumnChart
        :categories="charts.categories.value"
        :series="charts.revenue.value"
        :caption="T('revenue')"
        :category-label="t('dashboard.overview.categories.date')"
        :format-value="format.money"
        :format-tick="format.amountTick"
      />
    </OverviewPanel>
    <OverviewPanel data-panel="paths" :title="T('paths')" icon="route" :note="inPeriod">
      <ColumnChart
        :categories="charts.paths.value.categories"
        :series="charts.paths.value.series"
        :caption="T('paths')"
        :category-label="t('dashboard.overview.categories.path')"
        :format-value="format.count"
        label-all
      />
    </OverviewPanel>

    <OverviewPanel
      data-panel="average"
      :title="T('accreditedAverage')"
      icon="gauge"
      :note="inPeriod"
    >
      <GaugeChart
        :value="average?.value ?? null"
        :caption="T('accreditedAverage')"
        :value-text="format.percent(average?.value)"
        :note="averageNote"
      />
    </OverviewPanel>
    <OverviewPanel data-panel="governance" :title="T('governance')" icon="shield-info" :note="now">
      <SegmentedBar
        :segments="charts.governance.value"
        :caption="T('governance')"
        :format-value="format.count"
        :format-share="format.share"
      />
    </OverviewPanel>
    <OverviewPanel
      data-panel="regions"
      :title="W('entities_by_region')"
      icon="location"
      :note="now"
    >
      <ProgressList
        :rows="charts.regions.value"
        :caption="W('entities_by_region')"
        :format-value="format.count"
        :format-share="format.share"
        :empty-text="t('dashboard.widgets.empty')"
      />
    </OverviewPanel>

    <OverviewPanel data-panel="sectors" :title="W('entities_by_sector')" icon="layers" :note="now">
      <ProgressList
        :rows="charts.sectors.value"
        :caption="W('entities_by_sector')"
        :format-value="format.count"
        :format-share="format.share"
        :empty-text="t('dashboard.widgets.empty')"
      />
    </OverviewPanel>
    <OverviewPanel data-panel="decades" :title="T('decades')" icon="calendar" :note="now">
      <ColumnChart
        :categories="charts.decades.value.categories"
        :series="charts.decades.value.series"
        :caption="T('decades')"
        :category-label="t('dashboard.overview.categories.decade')"
        :format-value="format.count"
      />
    </OverviewPanel>
    <OverviewPanel
      data-panel="criteria"
      :title="W('lowest_criteria')"
      icon="warning-triangle"
      :note="now"
    >
      <WidgetCriteria :data="charts.widget('lowest_criteria').data" />
    </OverviewPanel>
  </div>
</template>
