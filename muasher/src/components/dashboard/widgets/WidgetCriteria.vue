<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'
import { percentText } from '@/utils/percentText'
import { formatNumber } from '@/utils/formatNumber'

const PERCENT = 100

const props = defineProps({
  data: { type: [Array, Object, null], default: null },
})

const { t, n, locale } = useI18n()
const rows = computed(() =>
  (Array.isArray(props.data) ? props.data : []).map((row) => ({
    code: row.code,
    name: localizedText(row.name, locale.value) || t('dashboard.widgets.unnamedCriterion'),
    percent: Number(row.averagePercent ?? 0),
    results: Number(row.results ?? 0),
  })),
)
</script>

<template>
  <p v-if="!rows.length" class="dashboard-widget__empty">{{ t('dashboard.widgets.empty') }}</p>
  <ol v-else class="widget-criteria">
    <li v-for="row in rows" :key="row.code" class="widget-criteria__row">
      <bdi class="widget-criteria__code" dir="ltr">{{ row.code }}</bdi>
      <span class="widget-criteria__name">{{ row.name }}</span>
      <svg class="widget-criteria__track" focusable="false" aria-hidden="true">
        <rect
          class="widget-criteria__fill"
          :width="`${Math.min(PERCENT, Math.max(0, row.percent))}%`"
          height="100%"
        />
      </svg>
      <bdi class="widget-criteria__percent" dir="ltr">{{ percentText(n, t, row.percent) }}</bdi>
      <span class="widget-criteria__results">{{
        t('dashboard.widgets.results', { count: formatNumber(n, row.results) })
      }}</span>
    </li>
  </ol>
</template>
