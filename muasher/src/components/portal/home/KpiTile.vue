<script setup>
import AppIcon from '@/components/ui/AppIcon.vue'
import DeltaChip from '@/components/charts/DeltaChip.vue'
import TrendSparkline from '@/components/charts/TrendSparkline.vue'

defineProps({
  icon: { type: String, required: true },
  label: { type: String, required: true },
  value: { type: String, required: true },
  caption: { type: String, default: '' },
  delta: { type: Number, default: null },
  trend: { type: Array, default: () => [] },
})
</script>

<template>
  <article class="kpi-tile">
    <p class="kpi-tile__label">
      <AppIcon :name="icon" class="kpi-tile__icon" />
      <span>{{ label }}</span>
    </p>
    <div class="kpi-tile__body">
      <div class="kpi-tile__figures">
        <bdi class="kpi-tile__value" dir="ltr">{{ value }}</bdi>
        <DeltaChip :value="delta" />
        <p v-if="caption" class="kpi-tile__caption">{{ caption }}</p>
      </div>
      <TrendSparkline v-if="trend.length > 1" class="kpi-tile__sparkline" :values="trend" />
    </div>
  </article>
</template>
