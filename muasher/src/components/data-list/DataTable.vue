<script setup>
import { computed, markRaw, onBeforeUnmount, ref, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import DataTableHeader from './DataTableHeader.vue'
import ColumnManager from './ColumnManager.vue'
import { columnKindOf } from '@/utils/tableColumns'
import { useScrollEdges } from '@/composables/useScrollEdges'
import { useTableColumnChoice } from '@/composables/useTableColumnChoice'
import { useListCard } from '@/composables/useListCard'

// Attributes (a class) belong to the table's box, not to the «الأعمدة» bar beside it.
defineOptions({ inheritAttrs: false })

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
  // The table's id in TABLE_COLUMNS: the viewer then chooses its optional columns («الأعمدة»).
  tableKey: { type: String, default: '' },
})

const emit = defineEmits(['retry', 'sort'])
const { t } = useI18n()

// Each cell shows its data in full; a list wider than its card scrolls sideways inside it (keyboard
// too, once it can scroll), with a shadow on the side that has more. Only free text is clamped.
const choice = props.tableKey
  ? useTableColumnChoice(toRef(props, 'tableKey'), toRef(props, 'columns'))
  : null
const shownColumns = computed(() => choice?.shown.value ?? props.columns)
const kinds = computed(() => shownColumns.value.map((column, index) => columnKindOf(column, index)))
const wrapRef = ref(null)
const edges = useScrollEdges(wrapRef)

// The «إدارة الأعمدة» button goes to the list's toolbar when there is one, else to a bar above the table.
const card = useListCard()
// Kept raw so the list card hands it over as is (its refs not unwrapped).
const menu = choice ? markRaw(choice) : null
if (card && menu) card.columnChoice = menu
onBeforeUnmount(() => {
  if (card?.columnChoice === menu) card.columnChoice = null
})
const ownsMenu = computed(() => Boolean(menu?.ordered.value.length) && !card?.hasToolbar)

function cellClass(index) {
  return `table__cell--${kinds.value[index]}`
}

// The full text of a clamped note, as its tooltip, when the pointer or the keyboard reaches it.
function revealFullText(event) {
  const target = event.target instanceof Element ? event.target.closest('.table__value') : null
  if (!target || target.hasAttribute('title')) return
  if (target.scrollHeight > target.clientHeight + 1)
    target.setAttribute('title', target.textContent.trim())
}

function keyOf(item, index) {
  return item[props.rowKey] ?? index
}
</script>

<template>
  <div v-if="ownsMenu" class="table-columns-bar">
    <ColumnManager :choice="menu" />
  </div>
  <div class="table-frame">
    <div
      ref="wrapRef"
      v-bind="$attrs"
      class="table-wrap"
      :class="{
        'table-wrap--stacked': stacked,
        'has-more-start': edges.moreAtStart.value,
        'has-more-end': edges.moreAtEnd.value,
      }"
      :tabindex="edges.scrollable.value ? 0 : undefined"
      :role="edges.scrollable.value ? 'region' : undefined"
      :aria-label="edges.scrollable.value ? t('components.table.scrollRegion') : undefined"
      @scroll.passive="edges.measure"
    >
      <table
        v-if="status === 'loading' || status === 'success'"
        class="table"
        :class="{ 'table--loading': status === 'loading' }"
        :aria-busy="status === 'loading' || undefined"
      >
        <thead class="table__head">
          <tr>
            <DataTableHeader
              v-for="(column, index) in shownColumns"
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
                v-for="(column, index) in shownColumns"
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
                v-for="(column, index) in shownColumns"
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
    <span
      class="table-frame__edge"
      :class="{ 'is-visible': edges.moreAtEnd.value }"
      aria-hidden="true"
    />
  </div>
</template>
