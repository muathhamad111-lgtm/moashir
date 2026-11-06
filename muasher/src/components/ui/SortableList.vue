<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useDragReorder } from '@/composables/useDragReorder'
import { moveIndex } from '@/utils/reorder'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  items: { type: Array, required: true },
  itemKey: { type: String, default: 'id' },
  labelOf: { type: Function, required: true },
  disabled: { type: Boolean, default: false },
  listLabel: { type: String, required: true },
})

const emit = defineEmits(['reorder'])
const { t } = useI18n()

function emitOrder(from, to) {
  emit(
    'reorder',
    moveIndex(props.items, from, to).map((item) => item[props.itemKey]),
  )
}

const drag = useDragReorder(emitOrder)

function step(index, offset) {
  emitOrder(index, index + offset)
}
</script>

<template>
  <ul class="sortable-list" :aria-label="listLabel" :aria-busy="disabled || undefined">
    <li
      v-for="(item, index) in items"
      :key="item[itemKey]"
      class="sortable-list__row"
      :class="{
        'is-dragging': drag.dragIndex.value === index,
        'is-over': drag.overIndex.value === index,
      }"
      :draggable="!disabled"
      :data-sortable-index="index"
      @dragstart="drag.start(index, $event)"
      @dragover.prevent="drag.over(index)"
      @drop.prevent="drag.drop(index)"
      @dragend="drag.end"
    >
      <span
        class="sortable-list__handle"
        :title="t('components.sortable.dragHint')"
        aria-hidden="true"
      >
        <AppIcon name="dots-vertical" />
      </span>
      <div class="sortable-list__content">
        <slot name="item" :item="item" :index="index" />
      </div>
      <div
        class="sortable-list__moves"
        role="group"
        :aria-label="t('components.sortable.moveGroup', { name: labelOf(item) })"
      >
        <BaseButton
          variant="ghost"
          size="sm"
          icon-only
          icon="arrow-up"
          :disabled="disabled || index === 0"
          :aria-label="t('components.sortable.moveUp', { name: labelOf(item) })"
          @click="step(index, -1)"
        />
        <BaseButton
          variant="ghost"
          size="sm"
          icon-only
          icon="arrow-down"
          :disabled="disabled || index === items.length - 1"
          :aria-label="t('components.sortable.moveDown', { name: labelOf(item) })"
          @click="step(index, 1)"
        />
      </div>
    </li>
  </ul>
</template>
