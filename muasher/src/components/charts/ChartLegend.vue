<script setup>
defineProps({
  items: { type: Array, required: true },
  // Toggle buttons that show or hide each series; the chart keeps one shown.
  toggleable: { type: Boolean, default: false },
  hidden: { type: Array, default: () => [] },
})

const emit = defineEmits(['toggle'])
</script>

<template>
  <ul class="chart-legend" :class="{ 'chart-legend--toggles': toggleable }">
    <li v-for="item in items" :key="item.key" class="chart-legend__item" :class="item.toneClass">
      <button
        v-if="toggleable"
        type="button"
        class="chart-legend__toggle"
        :aria-pressed="hidden.includes(item.key) ? 'false' : 'true'"
        :data-series="item.key"
        @click="emit('toggle', item.key)"
      >
        <span
          class="chart-legend__swatch"
          :style="{ '--chart-swatch': item.color }"
          aria-hidden="true"
        />
        <span class="chart-legend__label">{{ item.label }}</span>
      </button>
      <template v-else>
        <span
          class="chart-legend__swatch"
          :style="item.color ? { '--chart-swatch': item.color } : undefined"
          aria-hidden="true"
        />
        <bdi class="chart-legend__label">{{ item.label }}</bdi>
        <bdi v-if="item.value !== undefined" class="chart-legend__value" dir="ltr">{{
          item.value
        }}</bdi>
      </template>
    </li>
  </ul>
</template>
