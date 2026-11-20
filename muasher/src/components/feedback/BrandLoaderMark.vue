<script setup>
import { computed } from 'vue'

// The platform's waiting mark: the star drawn inside a ring. With a progress (0–1) the ring
// fills to it; without one the ring turns until the wait ends.
const props = defineProps({
  progress: { type: Number, default: null },
})

const STAR_PATH =
  'M12.82 92.5L0 86.09L12.78 79.71L17.84 77.19L32.72 69.75L40.44 65.87L57.41 57.41L65.83 40.54L68.79 34.68L77.23 17.73L77.25 17.73L78.45 15.31L86.09 0L93.74 15.29L94.92 17.65L103.38 34.63L106.25 40.32L114.78 57.41L131.67 65.86L139.57 69.8L154.16 77.09L159.51 79.75L172.21 86.09L159.45 92.46L154.06 95.18L139.39 102.5L131.39 106.5L114.78 114.82L106.3 131.77L103.44 137.48L94.94 154.54L93.76 156.86L86.09 172.21L78.43 156.86L65.77 131.53L57.41 114.82L40.82 106.52L32.88 102.52L17.95 95.08Z'

const isIndeterminate = computed(() => props.progress === null)
const offset = computed(() =>
  isIndeterminate.value ? null : (100 - Math.min(1, Math.max(0, props.progress)) * 100).toFixed(2),
)
</script>

<template>
  <svg
    class="brand-loader"
    :class="{ 'brand-loader--indeterminate': isIndeterminate }"
    viewBox="0 0 200 200"
    aria-hidden="true"
    focusable="false"
  >
    <circle class="brand-loader__track" cx="100" cy="100" r="92" />
    <circle
      class="brand-loader__ring"
      cx="100"
      cy="100"
      r="92"
      pathLength="100"
      :stroke-dashoffset="offset"
    />
    <g class="brand-loader__spin">
      <path
        class="brand-loader__star"
        pathLength="100"
        transform="translate(100 100) scale(0.62) translate(-86.1 -86.1)"
        :d="STAR_PATH"
      />
    </g>
  </svg>
</template>
