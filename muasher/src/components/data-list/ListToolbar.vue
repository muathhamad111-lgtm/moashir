<script setup>
import ExpandableSearch from './ExpandableSearch.vue'
import FilterDialog from './FilterDialog.vue'
import ColumnChooser from './ColumnChooser.vue'
import { useFilterDraft } from '@/composables/useFilterDraft'
import { useListCard } from '@/composables/useListCard'

const props = defineProps({
  search: { type: String, default: '' },
  searchPlaceholder: { type: String, default: '' },
  filterState: { type: Object, default: null },
  searchable: { type: Boolean, default: true },
})

const emit = defineEmits(['update:search'])
const filterDraft = props.filterState ? useFilterDraft(props.filterState) : null
// The list's «الأعمدة» menu sits here, between the filter and the export.
const card = useListCard()
if (card) card.hasToolbar = true
</script>

<template>
  <div class="toolbar filter-toolbar">
    <div v-if="$slots.actions" class="toolbar__actions">
      <slot name="actions" />
    </div>
    <div class="filter-toolbar__controls">
      <ExpandableSearch
        v-if="searchable"
        :model-value="search"
        :placeholder="searchPlaceholder"
        @update:model-value="emit('update:search', $event)"
      />
      <FilterDialog
        v-if="$slots.filters && filterDraft"
        :active-count="filterDraft.activeCount.value"
        @open="filterDraft.load"
        @apply="filterDraft.apply"
        @reset="filterDraft.resetDraft"
        @clear="filterDraft.clear"
      >
        <slot name="filters" :draft="filterDraft.draft" />
      </FilterDialog>
      <ColumnChooser
        v-if="card?.columnChoice?.optional.length"
        :columns="card.columnChoice.optional"
        :locked="card.columnChoice.locked"
        :visible="card.columnChoice.visible"
        @toggle="card.columnChoice.toggle"
        @reset="card.columnChoice.reset"
      />
      <slot name="export" />
    </div>
  </div>
</template>
