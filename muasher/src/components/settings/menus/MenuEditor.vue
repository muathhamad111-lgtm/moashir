<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import MenuTreeList from './MenuTreeList.vue'
import MenuItemPanel from './MenuItemPanel.vue'
import { useMenuEditor } from '@/composables/useMenuEditor'

const rows = defineModel('rows', { type: Array, required: true })

const props = defineProps({
  field: { type: String, required: true },
  legend: { type: String, required: true },
  hint: { type: String, default: '' },
  max: { type: Number, required: true },
  maxChildren: { type: Number, default: 0 },
  addLabel: { type: String, required: true },
  emptyText: { type: String, required: true },
  errorOf: { type: Function, required: true },
  disabled: { type: Boolean, default: false },
})

const { t } = useI18n()
const editor = useMenuEditor(rows)
const { selection } = editor

const selectedPath = computed(() => {
  const found = selection.value
  if (!found) return ''
  return found.parent
    ? `${props.field}.${found.parentIndex}.children.${found.index}`
    : `${props.field}.${found.index}`
})

function hasErrors(path) {
  return [path, `${path}.label.ar`, `${path}.url`, `${path}.children`].some((key) =>
    Boolean(props.errorOf(key)),
  )
}

function add() {
  if (props.maxChildren > 0) editor.addHeaderLink()
  else editor.addLink()
}
</script>

<template>
  <fieldset class="menu-editor">
    <legend class="site-settings__legend">{{ legend }}</legend>
    <p v-if="hint" class="site-settings__hint">{{ hint }}</p>
    <div class="menu-editor__layout">
      <div class="menu-editor__tree">
        <MenuTreeList
          v-if="rows.length"
          :rows="rows"
          :path="field"
          :selected-key="editor.selectedKey.value"
          :max-children="maxChildren"
          :has-errors="hasErrors"
          :disabled="disabled"
          @select="editor.select"
          @add-child="editor.addChild"
          @move="editor.move"
        />
        <p v-else class="site-settings__empty">{{ emptyText }}</p>
        <BaseButton
          variant="secondary"
          size="sm"
          icon="plus"
          :disabled="disabled || rows.length >= max"
          @click="add"
        >
          {{ addLabel }}
        </BaseButton>
      </div>
      <MenuItemPanel
        v-if="selection"
        :key="selectedPath"
        :item="selection.row"
        :path="selectedPath"
        :error-of="errorOf"
        :disabled="disabled"
        @remove="editor.remove"
      />
      <p v-else class="menu-editor__placeholder">{{ t('identity.menus.selectHint') }}</p>
    </div>
  </fieldset>
</template>
