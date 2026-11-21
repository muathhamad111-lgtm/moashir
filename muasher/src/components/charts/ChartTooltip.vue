<script setup>
defineProps({
  title: { type: String, required: true },
  rows: { type: Array, required: true },
  x: { type: Number, required: true },
  width: { type: Number, required: true },
})
</script>

<template>
  <div
    class="chart-tooltip"
    :class="{ 'chart-tooltip--flip': x > width / 2 }"
    :style="{ '--chart-tooltip-x': `${x}px` }"
    role="status"
  >
    <p class="chart-tooltip__title">{{ title }}</p>
    <dl class="chart-tooltip__rows">
      <div v-for="row in rows" :key="row.key" class="chart-tooltip__row">
        <dt>
          <span
            class="chart-tooltip__swatch"
            :style="{ '--chart-swatch': row.color }"
            aria-hidden="true"
          />{{ row.label }}
        </dt>
        <dd>
          <bdi dir="ltr">{{ row.value }}</bdi>
        </dd>
      </div>
    </dl>
  </div>
</template>
