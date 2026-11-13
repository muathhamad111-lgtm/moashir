<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import { barRows } from '@/utils/dashboard/barLabels'
import { formatNumber } from '@/utils/formatNumber'

const props = defineProps({
  definition: { type: Object, required: true },
  data: { type: [Array, Object, null], default: null },
})

const { t, te, n, locale } = useI18n()
const rows = computed(() => barRows(props.definition, props.data, { t, te, locale: locale.value }))
</script>

<template>
  <p v-if="!rows.length" class="dashboard-widget__empty">{{ t('dashboard.widgets.empty') }}</p>
  <ul v-else class="widget-bars">
    <li v-for="row in rows" :key="row.id" class="widget-bars__row">
      <span class="widget-bars__label">{{ row.label }}</span>
      <svg class="widget-bars__track" focusable="false" aria-hidden="true">
        <rect class="widget-bars__fill" :width="`${row.ratio}%`" height="100%" />
      </svg>
      <span class="widget-bars__value">
        <bdi dir="ltr">{{ formatNumber(n, row.count) }}</bdi>
        <MoneyAmount v-if="row.amount !== null" :value="String(row.amount)" />
      </span>
    </li>
  </ul>
</template>
