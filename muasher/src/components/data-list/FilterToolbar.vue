<script setup>
import ExpandableSearch from './ExpandableSearch.vue'
import FilterDialog from './FilterDialog.vue'

defineProps({
  search: { type: String, default: '' },
  searchPlaceholder: { type: String, default: '' },
  activeCount: { type: Number, default: 0 },
})

const emit = defineEmits(['update:search', 'open-filters', 'apply', 'reset', 'clear'])
</script>

<template>
  <div class="toolbar filter-toolbar">
    <div class="filter-toolbar__controls">
      <ExpandableSearch
        :model-value="search"
        :placeholder="searchPlaceholder"
        @update:model-value="emit('update:search', $event)"
      />
      <FilterDialog
        v-if="$slots.filters"
        :active-count="activeCount"
        @open="emit('open-filters')"
        @apply="emit('apply')"
        @reset="emit('reset')"
        @clear="emit('clear')"
      >
        <slot name="filters" />
      </FilterDialog>
    </div>
    <div v-if="$slots.actions" class="toolbar__actions">
      <slot name="actions" />
    </div>
  </div>
</template>
