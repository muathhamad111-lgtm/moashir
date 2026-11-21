<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { nextSort, SORT_DIRECTIONS, sortDirectionOf } from '@/utils/listSort'

// One column heading; a sortable column (with a `sortKey`) is a button that flips the order.
const props = defineProps({
  column: { type: Object, required: true },
  sort: { type: String, default: '' },
})

const emit = defineEmits(['sort'])
const { t } = useI18n()

const ARIA_SORT = { [SORT_DIRECTIONS.ASC]: 'ascending', [SORT_DIRECTIONS.DESC]: 'descending' }
const ICONS = { [SORT_DIRECTIONS.ASC]: 'arrow-up', [SORT_DIRECTIONS.DESC]: 'arrow-down' }

const direction = computed(() =>
  props.column.sortKey ? sortDirectionOf(props.sort, props.column.sortKey) : null,
)
const ariaSort = computed(() =>
  props.column.sortKey ? (ARIA_SORT[direction.value] ?? 'none') : undefined,
)

function choose() {
  emit('sort', nextSort(props.sort, props.column.sortKey, props.column.sortFirst))
}
</script>

<template>
  <th class="table__cell" scope="col" :aria-sort="ariaSort">
    <button
      v-if="column.sortKey"
      type="button"
      class="table__sort"
      :class="{ 'is-sorted': direction }"
      :title="t('components.table.sortBy', { column: column.label })"
      @click="choose"
    >
      <span>{{ column.label }}</span>
      <AppIcon
        :name="ICONS[direction] ?? 'chevron-down'"
        :mirror="false"
        class="table__sort-icon"
        aria-hidden="true"
      />
    </button>
    <template v-else>{{ column.label }}</template>
  </th>
</template>
