<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatRiyadhDate } from '@/utils/datetime'
import { formatKpiValue } from '@/utils/kpiNumber'

const props = defineProps({
  history: { type: Array, default: () => [] },
  unit: { type: String, default: '' },
  caption: { type: String, required: true },
})

const { t, n, locale } = useI18n()

const rows = computed(() =>
  [...props.history].reverse().map((entry) => ({
    key: entry.measuredOn,
    date: formatRiyadhDate(entry.measuredOn, locale.value),
    value: [formatKpiValue(n, entry.value), props.unit].filter(Boolean).join(' '),
  })),
)
</script>

<template>
  <table v-if="rows.length" class="kpi-history">
    <caption class="kpi-history__caption">
      {{
        caption
      }}
    </caption>
    <thead>
      <tr>
        <th scope="col" class="kpi-history__head">{{ t('kpis.history.date') }}</th>
        <th scope="col" class="kpi-history__head">{{ t('kpis.history.value') }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="row in rows" :key="row.key" class="kpi-history__row">
        <td class="kpi-history__cell">{{ row.date }}</td>
        <td class="kpi-history__cell kpi-history__cell--number">{{ row.value }}</td>
      </tr>
    </tbody>
  </table>
</template>
