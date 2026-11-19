<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import RankedBars from '@/components/charts/RankedBars.vue'
import { formatNumber } from '@/utils/formatNumber'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  data: { type: [Array, Object, null], default: null },
})

const { t, n, locale } = useI18n()
const rows = computed(() => (Array.isArray(props.data) ? props.data : []))
const items = computed(() =>
  rows.value.map((row) => ({
    key: row.code,
    name: localizedText(row.name, locale.value) || t('dashboard.widgets.unnamedCriterion'),
    percent: Number(row.averagePercent ?? 0),
    delta: null,
  })),
)
// The result count is the same for most criteria, so it is said once under the bars.
const resultsNote = computed(() => {
  const counts = rows.value.map((row) => Number(row.results ?? 0))
  if (!counts.length) return ''
  const [min, max] = [Math.min(...counts), Math.max(...counts)]
  return min === max
    ? t('dashboard.overview.criteria.results', { count: formatNumber(n, min) })
    : t('dashboard.overview.criteria.resultsRange', {
        min: formatNumber(n, min),
        max: formatNumber(n, max),
      })
})
</script>

<template>
  <p v-if="!items.length" class="dashboard-widget__empty">{{ t('dashboard.widgets.empty') }}</p>
  <div v-else class="overview-criteria">
    <RankedBars :items="items" :caption="t('dashboard.overview.criteria.caption')" />
    <p class="overview-criteria__note">{{ resultsNote }}</p>
  </div>
</template>
