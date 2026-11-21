<script setup>
import { computed } from 'vue'

const HUNDRED = 100

const props = defineProps({
  segments: { type: Array, required: true },
  caption: { type: String, required: true },
  formatValue: { type: Function, required: true },
  formatShare: { type: Function, required: true },
})

const total = computed(() => props.segments.reduce((sum, s) => sum + (Number(s.value) || 0), 0))
const parts = computed(() =>
  props.segments.map((segment) => ({
    ...segment,
    share: total.value ? ((Number(segment.value) || 0) / total.value) * HUNDRED : 0,
  })),
)
</script>

<template>
  <figure class="chart chart--segmented">
    <div class="chart-segmented__bar" aria-hidden="true">
      <span
        v-for="part in parts.filter((item) => item.share > 0)"
        :key="part.key"
        class="chart-segmented__part"
        :style="{ '--chart-series': part.color, flexGrow: part.share }"
      />
    </div>
    <ul class="chart-segmented__list" :aria-label="caption">
      <li
        v-for="part in parts"
        :key="part.key"
        class="chart-segmented__row"
        :data-segment="part.key"
      >
        <span
          class="chart-legend__swatch"
          :style="{ '--chart-swatch': part.color }"
          aria-hidden="true"
        />
        <bdi class="chart-segmented__label">{{ part.label }}</bdi>
        <bdi class="chart-segmented__share" dir="ltr">{{ formatShare(part.share) }}</bdi>
        <bdi class="chart-segmented__count" dir="ltr">({{ formatValue(part.value) }})</bdi>
      </li>
    </ul>
  </figure>
</template>
