<script setup>
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import DonutChart from '@/components/charts/DonutChart.vue'
import SegmentedBar from '@/components/charts/SegmentedBar.vue'
import ProgressList from '@/components/charts/ProgressList.vue'
import OverviewPanel from '../overview/OverviewPanel.vue'
import BoardRow from './BoardRow.vue'
import { useOverviewCharts } from '@/composables/useOverviewCharts'
import { useOverviewFormats } from '@/composables/useOverviewFormats'
import { useBoardBreakdowns } from '@/composables/useBoardBreakdowns'

// Entity rows: governance as a large donut beside the assessor workload (1/2 + 1/2), then who the
// entities are in four quarter cards (sector, region, type and size, founding decade).
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
// At a quarter width the founding decades read better as bars than as columns.
const decades = computed(() =>
  charts.decades.value.categories.map((label, index) => ({
    key: label,
    label,
    value: charts.decades.value.series[0]?.values[index] ?? 0,
  })),
)
const profile = computed(() => [
  { key: 'types', segments: board.types.value },
  { key: 'sizes', segments: board.sizes.value },
])
</script>

<template>
  <BoardRow label="governance">
    <OverviewPanel
      class="board-panel--lg"
      data-panel="governance"
      :title="T('governance')"
      icon="shield-info"
      :note="scope('now')"
    >
      <DonutChart
        :segments="charts.governance.value"
        :caption="T('governance')"
        :category-label="t('dashboard.overview.categories.band')"
        :value-label="t('dashboard.overview.categories.entities')"
        :total-label="t('dashboard.overview.categories.entities')"
        :format-value="format.count"
        :format-share="format.share"
      />
    </OverviewPanel>
    <OverviewPanel
      data-panel="workload"
      :title="T('workload')"
      icon="users-two"
      :note="scope('workload')"
    >
      <ProgressList
        :rows="board.workload.value"
        :caption="T('workload')"
        :format-value="format.count"
        :format-share="format.share"
        :empty-text="t('dashboard.widgets.empty')"
      />
    </OverviewPanel>
  </BoardRow>

  <BoardRow label="entities">
    <OverviewPanel
      data-panel="sectors"
      :title="T('sectors')"
      icon="layers"
      :note="scope('asOfEnd')"
    >
      <ProgressList
        :rows="charts.sectors.value"
        :caption="T('sectors')"
        :format-value="format.count"
        :format-share="format.share"
        :empty-text="t('dashboard.widgets.empty')"
      />
    </OverviewPanel>
    <OverviewPanel
      data-panel="regions"
      :title="T('regions')"
      icon="location"
      :note="scope('asOfEnd')"
    >
      <ProgressList
        :rows="board.regions.value"
        :caption="T('regions')"
        :format-value="format.count"
        :format-share="format.share"
        :empty-text="t('dashboard.widgets.empty')"
      />
    </OverviewPanel>
    <OverviewPanel
      data-panel="profile"
      :title="T('profile')"
      icon="building"
      :note="scope('asOfEnd')"
    >
      <div class="board-split">
        <section v-for="part in profile" :key="part.key" class="board-split__part">
          <h4 class="board-split__title">{{ T(part.key) }}</h4>
          <SegmentedBar
            :segments="part.segments"
            :caption="T(part.key)"
            :format-value="format.count"
            :format-share="format.share"
          />
        </section>
      </div>
    </OverviewPanel>
    <OverviewPanel
      data-panel="decades"
      :title="T('decades')"
      icon="calendar"
      :note="scope('asOfEnd')"
    >
      <ProgressList
        :rows="decades"
        :caption="T('decades')"
        :format-value="format.count"
        :format-share="format.share"
        :empty-text="t('dashboard.widgets.empty')"
      />
    </OverviewPanel>
  </BoardRow>
</template>
