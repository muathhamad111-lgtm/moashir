<script setup>
import { computed } from 'vue'
import CriteriaTreeRow from './CriteriaTreeRow.vue'
import CriteriaTreeList from './CriteriaTreeList.vue'
import { useDragReorder } from '@/composables/useDragReorder'
import { moveIndex } from '@/utils/reorder'

const props = defineProps({
  keys: { type: Array, required: true },
  index: { type: Map, required: true },
  visible: { type: Set, default: null },
  expanded: { type: Set, required: true },
  selectedKey: { type: String, default: null },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'toggle', 'add', 'reorder'])

const shownKeys = computed(() =>
  props.keys.filter((key) => !props.visible || props.visible.has(key)),
)
const canReorder = computed(() => props.canEdit && !props.visible && props.keys.length > 1)

function itemOf(key) {
  return props.index.get(key)
}

function isOpen(key) {
  return Boolean(props.visible) || props.expanded.has(key)
}

function emitOrder(from, to) {
  const ordered = moveIndex(props.keys, from, to)
  emit('reorder', {
    type: itemOf(props.keys[0]).type,
    parentKey: itemOf(props.keys[0]).parentKey,
    ids: ordered.map((key) => itemOf(key).node.id),
  })
}

const drag = useDragReorder(emitOrder)

function positionOf(position) {
  return canReorder.value ? { index: position, count: props.keys.length } : null
}
</script>

<template>
  <ul class="criteria-tree__list" role="list">
    <li
      v-for="(key, position) in shownKeys"
      :key="key"
      class="criteria-tree__item"
      :class="{
        'is-dragging': drag.dragIndex.value === position,
        'is-over': drag.overIndex.value === position,
      }"
      :draggable="canReorder"
      @dragstart.stop="drag.start(position, $event)"
      @dragover.prevent.stop="drag.over(position)"
      @drop.prevent.stop="drag.drop(position)"
      @dragend.stop="drag.end"
    >
      <CriteriaTreeRow
        :item="itemOf(key)"
        :selected="key === selectedKey"
        :expanded="isOpen(key)"
        :can-add="canEdit"
        :position="positionOf(position)"
        @select="emit('select', key)"
        @toggle="emit('toggle', key)"
        @add="emit('add', key)"
        @move="emitOrder(position, position + $event)"
      />
      <CriteriaTreeList
        v-if="itemOf(key).children.length && isOpen(key)"
        :keys="itemOf(key).children"
        :index="index"
        :visible="visible"
        :expanded="expanded"
        :selected-key="selectedKey"
        :can-edit="canEdit"
        @select="emit('select', $event)"
        @toggle="emit('toggle', $event)"
        @add="emit('add', $event)"
        @reorder="emit('reorder', $event)"
      />
    </li>
  </ul>
</template>
