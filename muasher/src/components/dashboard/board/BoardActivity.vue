<script setup>
import { toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import ColumnChart from '@/components/charts/ColumnChart.vue'
import DonutChart from '@/components/charts/DonutChart.vue'
import SegmentedBar from '@/components/charts/SegmentedBar.vue'
import OverviewPanel from '../overview/OverviewPanel.vue'
import OverviewGeography from '../overview/OverviewGeography.vue'
import BoardRow from './BoardRow.vue'
import BoardPeriodBars from './BoardPeriodBars.vue'
import { useOverviewCharts } from '@/composables/useOverviewCharts'
import { useOverviewFormats } from '@/composables/useOverviewFormats'
import { useBoardBreakdowns } from '@/composables/useBoardBreakdowns'

// Activity rows: the period figures at full width, the map (2/3) beside the levels (1/3), then the
// request and payment states in three equal cards.
const props = defineProps({
  overview: { type: Object, required: true },
})

const { t } = useI18n()
const overview = toRef(props, 'overview')
const charts = useOverviewCharts(overview)
const board = useBoardBreakdowns(overview)
const format = useOverviewFormats()
const T = (key) => t(`dashboard.board.titles.${key}`)
const scope = (key) => t(`dashboard.board.scope.${key}`)
</script>

<template>
  <BoardRow label="periods">
    <OverviewPanel
      class="board-panel--lg"
      data-panel="periods"
      :title="T('periods')"
      icon="chart-bar"
      :note="scope('period')"
    >
      <BoardPeriodBars
        :categories="charts.categories.value"
        :series="overview.trends.series"
        :height="320"
      />
    </OverviewPanel>
  </BoardRow>

  <BoardRow label="geography">
    <OverviewPanel
      class="board-panel--lg"
      data-panel="geography"
      :title="T('geography')"
      icon="location"
      :note="t('dashboard.overview.geography.note')"
      style="--board-span: 2"
    >
      <OverviewGeography :overview="overview" />
    </OverviewPanel>
    <OverviewPanel
      class="board-panel--lg"
      data-panel="levels"
      :title="T('levels')"
      icon="award"
      :note="scope('asOfEnd')"
    >
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
  </BoardRow>

  <BoardRow label="states">
    <OverviewPanel
      data-panel="request-states"
      :title="T('requestStates')"
      icon="docs"
      :note="scope('period')"
    >
      <SegmentedBar
        :segments="board.requestStates.value"
        :caption="T('requestStates')"
        :format-value="format.count"
        :format-share="format.share"
      />
    </OverviewPanel>
    <OverviewPanel data-panel="paths" :title="T('paths')" icon="layers" :note="scope('period')">
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
      data-panel="payment-states"
      :title="T('paymentStates')"
      icon="sar"
      :note="scope('period')"
    >
      <SegmentedBar
        :segments="board.paymentStates.value"
        :caption="T('paymentStates')"
        :format-value="format.count"
        :format-share="format.share"
      />
    </OverviewPanel>
  </BoardRow>
</template>
