<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'

// Progress to a target, one row each: the label, the reached share and a bar. `value` and
// `target` are counts; the share is capped at 100% for the bar but printed as it is.
const HUNDRED = 100

const props = defineProps({
  rows: { type: Array, required: true },
  caption: { type: String, required: true },
})

const { t, n } = useI18n()
const items = computed(() =>
  props.rows.map((row) => {
    const share = row.target ? (row.value / row.target) * HUNDRED : 0
    return {
      ...row,
      share,
      shareText: t('public.home.percent', { value: formatNumber(n, share.toFixed(0)) }),
      figures: `${formatNumber(n, row.value)} / ${formatNumber(n, row.target)}`,
    }
  }),
)
</script>

<template>
  <ul class="board-targets" :aria-label="caption">
    <li v-for="item in items" :key="item.key" class="board-targets__row">
      <bdi class="board-targets__label">{{ item.label }}</bdi>
      <span class="board-targets__figures">
        <bdi class="board-targets__count" dir="ltr">{{ item.figures }}</bdi>
        <bdi dir="ltr">{{ item.shareText }}</bdi>
      </span>
      <span class="board-targets__track" aria-hidden="true">
        <span
          class="board-targets__fill"
          :style="{ inlineSize: `${Math.min(item.share, HUNDRED)}%` }"
        />
      </span>
    </li>
  </ul>
</template>
