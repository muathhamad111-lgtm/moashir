<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import DataTableHeader from './DataTableHeader.vue'
import { columnKindOf } from '@/utils/tableColumns'

const props = defineProps({
  columns: { type: Array, required: true },
  items: { type: Array, default: () => [] },
  status: {
    type: String,
    default: 'success',
    validator: (value) => ['loading', 'empty', 'error', 'success'].includes(value),
  },
  rowKey: { type: String, default: 'id' },
  skeletonRows: { type: Number, default: 5 },
  emptyTitle: { type: String, default: '' },
  emptyMessage: { type: String, default: '' },
  errorMessage: { type: String, default: '' },
  // The current sort ("column" or "-column"); columns with a `sortKey` sort on click.
  sort: { type: String, default: '' },
  // When the list itself is narrow (a phone, or a column beside a side panel), each row reads as a
  // block of label/value lines: the first column as its title and the actions column last.
  stacked: { type: Boolean, default: false },
})

const emit = defineEmits(['retry', 'sort'])
const { t } = useI18n()

// Each cell reads on one line (two for a primary cell with its muted detail); text longer than its
// column's width ends with an ellipsis and shows in full on hover or focus.
const kinds = computed(() => props.columns.map((column, index) => columnKindOf(column, index)))

function cellClass(index) {
  const kind = `table__cell--${kinds.value[index]}`
  return props.columns[index].optional ? [kind, 'table__cell--optional'] : kind
}

// The full text of a cut-off value, as its tooltip, when the pointer or the keyboard reaches it.
function revealFullText(event) {
  const target =
    event.target instanceof Element ? event.target.closest('.table__value *, .table__value') : null
  if (!target || target.hasAttribute('title')) return
  if (target.scrollWidth > target.clientWidth)
    target.setAttribute('title', target.textContent.trim())
}

function keyOf(item, index) {
  return item[props.rowKey] ?? index
}
</script>

<template>
  <div class="table-wrap" :class="{ 'table-wrap--stacked': stacked }">
    <table
      v-if="status === 'loading' || status === 'success'"
      class="table"
      :class="{ 'table--loading': status === 'loading' }"
      :aria-busy="status === 'loading' || undefined"
    >
      <thead class="table__head">
        <tr>
          <DataTableHeader
            v-for="(column, index) in columns"
            :key="column.key"
            :class="cellClass(index)"
            :column="column"
            :sort="sort"
            @sort="emit('sort', $event)"
          />
        </tr>
      </thead>
      <tbody @mouseover="revealFullText" @focusin="revealFullText">
        <template v-if="status === 'loading'">
          <tr v-for="row in skeletonRows" :key="`skeleton-${row}`" class="table__row">
            <td
              v-for="(column, index) in columns"
              :key="column.key"
              class="table__cell"
              :class="cellClass(index)"
              :data-label="stacked ? column.label : undefined"
            >
              <Skeleton variant="text" />
            </td>
          </tr>
        </template>
        <template v-else>
          <tr v-for="(item, index) in items" :key="keyOf(item, index)" class="table__row">
            <td
              v-for="(column, index) in columns"
              :key="column.key"
              class="table__cell"
              :class="cellClass(index)"
              :data-label="stacked ? column.label : undefined"
            >
              <div class="table__value">
                <slot :name="`cell-${column.key}`" :item="item">
                  {{ item[column.key] }}
                </slot>
              </div>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
    <EmptyState
      v-else-if="status === 'empty'"
      :title="emptyTitle || t('common.empty')"
      :message="emptyMessage"
    >
      <template v-if="$slots['empty-action']" #action>
        <slot name="empty-action" />
      </template>
    </EmptyState>
    <ErrorState v-else :message="errorMessage" @retry="emit('retry')" />
  </div>
</template>
