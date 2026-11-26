<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ColumnChart from '@/components/charts/ColumnChart.vue'
import { useOverviewFormats } from '@/composables/useOverviewFormats'

// «In each period»: one measure per bucket of the selected period as columns, beside the same
// figures as a period / count list. The switch picks the measure; the bucket (day, week, month)
// follows the period length.
const MEASURES = Object.freeze(['requests', 'entities', 'completed', 'collected'])
const MONEY = 'collected'

const props = defineProps({
  categories: { type: Array, required: true },
  series: { type: Object, required: true },
  height: { type: Number, default: 260 },
})

const { t } = useI18n()
const format = useOverviewFormats()
const measure = ref(MEASURES[0])
const formatValue = computed(() => (measure.value === MONEY ? format.money : format.count))
const values = computed(() => props.series[measure.value] ?? [])
const label = computed(() => t(`dashboard.overview.series.${measure.value}`))
const chartSeries = computed(() => [
  { key: measure.value, label: label.value, values: values.value, color: 'var(--chart-single)' },
])
// The list keeps the latest buckets on top; empty ones stay so the periods read as a calendar.
const rows = computed(() =>
  props.categories
    .map((category, index) => ({ key: `${index}`, category, value: values.value[index] ?? 0 }))
    .reverse(),
)
const total = computed(() => values.value.reduce((sum, value) => sum + (Number(value) || 0), 0))
</script>

<template>
  <div class="board-periods">
    <div
      class="overview-period__presets board-periods__switch"
      role="group"
      :aria-label="t('dashboard.board.periods.measure')"
    >
      <button
        v-for="item in MEASURES"
        :key="item"
        type="button"
        class="overview-period__preset"
        :class="{ 'is-active': measure === item }"
        :aria-pressed="measure === item ? 'true' : 'false'"
        @click="measure = item"
      >
        {{ t(`dashboard.overview.series.${item}`) }}
      </button>
    </div>
    <div class="board-periods__body">
      <ColumnChart
        class="board-periods__chart"
        :categories="categories"
        :series="chartSeries"
        :caption="label"
        :category-label="t('dashboard.overview.categories.date')"
        :format-value="formatValue"
        :format-tick="measure === MONEY ? format.amountTick : null"
        :height="height"
      />
      <div class="board-periods__list">
        <p class="board-periods__total">
          <span>{{ t('dashboard.board.periods.total') }}</span>
          <bdi dir="ltr">{{ formatValue(total) }}</bdi>
        </p>
        <ol class="board-periods__rows" :aria-label="label">
          <li v-for="row in rows" :key="row.key" class="board-periods__row">
            <span>{{ row.category }}</span>
            <bdi dir="ltr">{{ formatValue(row.value) }}</bdi>
          </li>
        </ol>
      </div>
    </div>
  </div>
</template>
