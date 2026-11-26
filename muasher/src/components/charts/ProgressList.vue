<script setup>
import { computed } from 'vue'

const HUNDRED = 100

const props = defineProps({
  rows: { type: Array, required: true },
  caption: { type: String, required: true },
  formatValue: { type: Function, required: true },
  formatShare: { type: Function, required: true },
  emptyText: { type: String, required: true },
})

const total = computed(() => props.rows.reduce((sum, row) => sum + (Number(row.value) || 0), 0))
const items = computed(() =>
  props.rows.map((row) => ({
    ...row,
    share: total.value ? ((Number(row.value) || 0) / total.value) * HUNDRED : 0,
  })),
)
</script>

<template>
  <p v-if="!rows.length" class="chart-progress__empty">{{ emptyText }}</p>
  <ul v-else class="chart-progress" :aria-label="caption">
    <li v-for="item in items" :key="item.key" class="chart-progress__row">
      <bdi class="chart-progress__label">{{ item.label }}</bdi>
      <span class="chart-progress__figures">
        <bdi dir="ltr">{{ formatShare(item.share) }}</bdi>
        <bdi class="chart-progress__count" dir="ltr">({{ formatValue(item.value) }})</bdi>
      </span>
      <span class="chart-progress__track" aria-hidden="true">
        <span class="chart-progress__fill" :style="{ inlineSize: `${item.share}%` }" />
      </span>
    </li>
  </ul>
</template>
