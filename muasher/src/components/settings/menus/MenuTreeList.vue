<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useDragReorder } from '@/composables/useDragReorder'
import { rowKeyOf } from '@/utils/rowKey'

const props = defineProps({
  rows: { type: Array, required: true },
  path: { type: String, required: true },
  selectedKey: { type: String, default: null },
  maxChildren: { type: Number, default: 0 },
  nested: { type: Boolean, default: false },
  hasErrors: { type: Function, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'add-child', 'move'])

const { t, locale } = useI18n()
const drag = useDragReorder((from, to) => emit('move', props.rows, from, to))

function labelOf(row) {
  const other = locale.value === 'ar' ? 'en' : 'ar'
  return row.label[locale.value].trim() || row.label[other].trim() || t('identity.menus.untitled')
}

function childPath(index) {
  return `${props.path}.${index}.children`
}
</script>

<template>
  <ul class="menu-tree" :class="{ 'menu-tree--nested': nested }">
    <li
      v-for="(row, index) in rows"
      :key="rowKeyOf(row)"
      class="menu-tree__row"
      :class="{
        'is-dragging': drag.dragIndex.value === index,
        'is-over': drag.overIndex.value === index,
      }"
      :draggable="!disabled"
      @dragstart.stop="drag.start(index, $event)"
      @dragover.prevent.stop="drag.over(index)"
      @drop.prevent.stop="drag.drop(index)"
      @dragend.stop="drag.end"
    >
      <div
        class="menu-tree__item"
        :class="{
          'is-selected': rowKeyOf(row) === selectedKey,
          'is-hidden': !row.isVisible,
          'is-invalid': hasErrors(`${path}.${index}`),
        }"
      >
        <span
          class="menu-tree__handle"
          :title="t('components.sortable.dragHint')"
          aria-hidden="true"
        >
          <AppIcon name="dots-vertical" />
        </span>
        <button
          type="button"
          class="menu-tree__select"
          :aria-current="rowKeyOf(row) === selectedKey ? 'true' : undefined"
          @click="emit('select', row)"
        >
          <span class="menu-tree__label">{{ labelOf(row) }}</span>
          <Badge v-if="!row.isVisible" variant="muted">{{ t('identity.menus.hiddenBadge') }}</Badge>
          <Badge v-if="row.children?.length" variant="info">
            {{ t('identity.menus.groupBadge', { count: row.children.length }) }}
          </Badge>
          <Badge v-if="hasErrors(`${path}.${index}`)" variant="danger">
            {{ t('identity.menus.invalidBadge') }}
          </Badge>
        </button>
        <div
          class="menu-tree__actions"
          role="group"
          :aria-label="t('components.sortable.moveGroup', { name: labelOf(row) })"
        >
          <BaseButton
            v-if="maxChildren > 0"
            variant="ghost"
            size="sm"
            icon="plus"
            icon-only
            :disabled="disabled || (row.children?.length ?? 0) >= maxChildren"
            :aria-label="t('identity.menus.addChild', { name: labelOf(row) })"
            @click="emit('add-child', row)"
          />
          <BaseButton
            variant="ghost"
            size="sm"
            icon="arrow-up"
            icon-only
            :disabled="disabled || index === 0"
            :aria-label="t('components.sortable.moveUp', { name: labelOf(row) })"
            @click="emit('move', rows, index, index - 1)"
          />
          <BaseButton
            variant="ghost"
            size="sm"
            icon="arrow-down"
            icon-only
            :disabled="disabled || index === rows.length - 1"
            :aria-label="t('components.sortable.moveDown', { name: labelOf(row) })"
            @click="emit('move', rows, index, index + 1)"
          />
        </div>
      </div>
      <MenuTreeList
        v-if="row.children?.length"
        nested
        :rows="row.children"
        :path="childPath(index)"
        :selected-key="selectedKey"
        :has-errors="hasErrors"
        :disabled="disabled"
        @select="(child) => emit('select', child)"
        @move="(list, from, to) => emit('move', list, from, to)"
      />
    </li>
  </ul>
</template>
