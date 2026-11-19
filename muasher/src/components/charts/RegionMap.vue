<script setup>
import { computed } from 'vue'
import { SAUDI_MAP_SIZE, SAUDI_REGION_PATHS } from './maps/saudiRegions'

const props = defineProps({
  regions: { type: Array, required: true },
  cities: { type: Array, default: () => [] },
  selected: { type: String, default: '' },
  caption: { type: String, required: true },
})

const emit = defineEmits(['select'])

const shapes = computed(() =>
  props.regions
    .filter((region) => SAUDI_REGION_PATHS[region.key])
    .map((region) => ({ ...region, path: SAUDI_REGION_PATHS[region.key] })),
)
// Larger dots first, so a small city is never hidden under a big one.
const dots = computed(() =>
  props.cities.filter((city) => city.x !== null).sort((a, b) => b.radius - a.radius),
)
</script>

<template>
  <svg
    class="region-map"
    :viewBox="`0 0 ${SAUDI_MAP_SIZE.width} ${SAUDI_MAP_SIZE.height}`"
    role="group"
    :aria-label="caption"
    focusable="false"
  >
    <path
      v-for="region in shapes"
      :key="region.key"
      class="region-map__region"
      :class="[
        `region-map__region--step-${region.step}`,
        { 'is-selected': selected === region.key },
      ]"
      :d="region.path"
      :data-region="region.key"
      role="button"
      tabindex="0"
      :aria-pressed="selected === region.key ? 'true' : 'false'"
      :aria-label="`${region.label}: ${region.display}`"
      @click="emit('select', region.key)"
      @keydown.enter.prevent="emit('select', region.key)"
      @keydown.space.prevent="emit('select', region.key)"
    >
      <title>{{ region.label }}: {{ region.display }}</title>
    </path>
    <g class="region-map__cities" aria-hidden="true">
      <circle
        v-for="city in dots"
        :key="city.key"
        class="region-map__city"
        :data-city="city.key"
        :cx="city.x"
        :cy="city.y"
        :r="city.radius"
        @click="emit('select', city.regionKey)"
      >
        <title>{{ city.label }}: {{ city.display }}</title>
      </circle>
    </g>
  </svg>
</template>
