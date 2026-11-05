<script setup>
import Skeleton from '@/components/feedback/Skeleton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  stats: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  skeletonCount: { type: Number, default: 4 },
})
</script>

<template>
  <div class="stats-cards">
    <template v-if="loading">
      <div v-for="index in skeletonCount" :key="index" class="stats-card">
        <Skeleton variant="circle" />
        <Skeleton variant="text" :count="2" />
      </div>
    </template>
    <template v-else>
      <div v-for="stat in stats" :key="stat.id" class="stats-card">
        <span v-if="stat.icon" class="stats-card__icon" aria-hidden="true">
          <AppIcon :name="stat.icon" />
        </span>
        <span class="stats-card__value">{{ stat.value }}</span>
        <span class="stats-card__label">{{ stat.label }}</span>
      </div>
    </template>
  </div>
</template>
