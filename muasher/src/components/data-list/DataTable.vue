<script setup>
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'

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
})

const emit = defineEmits(['retry'])
const { t } = useI18n()

function keyOf(item, index) {
  return item[props.rowKey] ?? index
}
</script>

<template>
  <div class="table-wrap">
    <table v-if="status === 'loading' || status === 'success'" class="table" :class="{ 'table--loading': status === 'loading' }" :aria-busy="status === 'loading' || undefined">
      <thead class="table__head">
        <tr>
          <th v-for="column in columns" :key="column.key" class="table__cell" scope="col">
            {{ column.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <template v-if="status === 'loading'">
          <tr v-for="row in skeletonRows" :key="`skeleton-${row}`" class="table__row">
            <td v-for="column in columns" :key="column.key" class="table__cell">
              <Skeleton variant="text" />
            </td>
          </tr>
        </template>
        <template v-else>
          <tr v-for="(item, index) in items" :key="keyOf(item, index)" class="table__row">
            <td v-for="column in columns" :key="column.key" class="table__cell">
              <slot :name="`cell-${column.key}`" :item="item">
                {{ item[column.key] }}
              </slot>
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
