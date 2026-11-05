<script setup>
import { RouterLink } from 'vue-router'
import PathBadge from '@/components/ui/PathBadge.vue'

defineProps({
  entries: { type: Array, required: true },
  current: { type: String, required: true },
  routeName: { type: String, required: true },
  label: { type: String, required: true },
  keptQuery: { type: Object, default: () => ({}) },
})
</script>

<template>
  <nav class="path-switch" :aria-label="label">
    <RouterLink
      v-for="entry in entries"
      :key="entry.path"
      class="path-switch__link"
      :class="{ 'is-active': entry.path === current }"
      :to="{ name: routeName, query: { ...keptQuery, path: entry.path } }"
      :aria-current="entry.path === current ? 'page' : undefined"
    >
      <PathBadge :path="entry.path" :label="entry.pathLabel" />
      <span v-if="entry.countLabel" class="path-switch__count">{{ entry.countLabel }}</span>
    </RouterLink>
  </nav>
</template>
