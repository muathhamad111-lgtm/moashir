<script setup>
import Skeleton from '@/components/feedback/Skeleton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

// selectable: each card is a toggle button that picks its list filter (`active` is the picked card id).
defineProps({
  stats: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  skeletonCount: { type: Number, default: 4 },
  selectable: { type: Boolean, default: false },
  active: { type: String, default: '' },
})

const emit = defineEmits(['select'])
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
      <component
        :is="selectable ? 'button' : 'div'"
        v-for="stat in stats"
        :key="stat.id"
        class="stats-card"
        :class="{
          'stats-card--selectable': selectable,
          'is-active': selectable && stat.id === active,
        }"
        :type="selectable ? 'button' : undefined"
        :aria-pressed="selectable ? String(stat.id === active) : undefined"
        @click="selectable && emit('select', stat.id)"
      >
        <span v-if="stat.icon" class="stats-card__icon" aria-hidden="true">
          <AppIcon :name="stat.icon" />
        </span>
        <span class="stats-card__value">{{ stat.value }}</span>
        <span class="stats-card__label">{{ stat.label }}</span>
        <span v-if="stat.detail" class="stats-card__detail">{{ stat.detail }}</span>
      </component>
    </template>
  </div>
</template>
