<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { gaugeArc } from '@/utils/charts/arcs'
import { formatNumber } from '@/utils/formatNumber'
import { formatRiyadhDateTime } from '@/utils/datetime'

// The storage counter: a half gauge of the used share of the quota, then total / used / available
// and what the selected period added. The tone turns warning past the configured threshold.
const ARC = Object.freeze({ cx: 100, cy: 100, radius: 84 })
const HUNDRED = 100
const GIGABYTE = 1024 ** 3

const props = defineProps({
  storage: { type: Object, required: true },
})

const { t, n, locale } = useI18n()

const share = computed(() =>
  props.storage.quotaBytes ? (props.storage.usedBytes / props.storage.quotaBytes) * HUNDRED : 0,
)
const tone = computed(() => {
  if (share.value >= props.storage.criticalPercent) return 'critical'
  return share.value >= props.storage.warningPercent ? 'warning' : 'good'
})

function size(bytes) {
  const gigabytes = bytes / GIGABYTE
  return t('dashboard.board.storage.gb', {
    value: formatNumber(n, gigabytes >= 10 ? gigabytes.toFixed(0) : gigabytes.toFixed(1)),
  })
}

const rows = computed(() => [
  { key: 'total', value: size(props.storage.quotaBytes) },
  { key: 'used', value: size(props.storage.usedBytes) },
  { key: 'available', value: size(props.storage.quotaBytes - props.storage.usedBytes) },
  { key: 'added', value: size(props.storage.addedInPeriodBytes) },
])
const measuredText = computed(() =>
  props.storage.measuredAt ? formatRiyadhDateTime(props.storage.measuredAt, locale.value) : '',
)
const percentText = computed(() =>
  t('public.home.percent', { value: formatNumber(n, share.value.toFixed(0)) }),
)
</script>

<template>
  <div class="board-storage" :data-tone="tone">
    <figure class="board-storage__gauge">
      <svg
        viewBox="0 0 200 108"
        role="img"
        :aria-label="t('dashboard.board.storage.gaugeLabel', { percent: percentText })"
        focusable="false"
      >
        <path class="board-storage__track" :d="gaugeArc(ARC, 1)" />
        <path v-if="share > 0" class="board-storage__fill" :d="gaugeArc(ARC, share / HUNDRED)" />
      </svg>
      <figcaption class="board-storage__value">
        <bdi dir="ltr">{{ percentText }}</bdi>
        <span>{{ t('dashboard.board.storage.usedShare') }}</span>
      </figcaption>
    </figure>
    <dl class="board-storage__rows">
      <div v-for="row in rows" :key="row.key" class="board-storage__row" :data-row="row.key">
        <dt>{{ t(`dashboard.board.storage.rows.${row.key}`) }}</dt>
        <dd>
          <bdi dir="ltr">{{ row.value }}</bdi>
        </dd>
      </div>
    </dl>
    <p v-if="measuredText" class="board-storage__measured">
      {{ t('dashboard.board.storage.measuredAt') }}
      <time :datetime="storage.measuredAt">{{ measuredText }}</time>
    </p>
    <p v-if="tone !== 'good'" class="board-storage__alert" role="status">
      {{ t(`dashboard.board.storage.alert.${tone}`) }}
    </p>
  </div>
</template>
