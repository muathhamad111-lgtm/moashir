<script setup>
import { computed, nextTick, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCompactViewport } from '@/composables/useCompactViewport'
import { useComboboxListbox } from '@/composables/useComboboxListbox'
import { DROPDOWN_PLACEMENTS } from '@/constants/dropdown'
import { valueKey } from '@/utils/dropdown/optionValue'
import FormField from '@/components/form/FormField.vue'
import DropdownSurface from '@/components/ui/DropdownSurface.vue'
import DropdownListbox from '@/components/ui/DropdownListbox.vue'
import DropdownTag from '@/components/ui/DropdownTag.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: [String, Number, Array, null], default: null },
  options: { type: Array, default: () => [] },
  label: { type: String, required: true },
  name: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  hint: { type: String, default: '' },
  error: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  multiple: { type: Boolean, default: false },
  remote: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'search', 'open', 'close', 'blur'])

const { t } = useI18n()
const baseId = useId()
const listboxId = `${baseId}-listbox`
const fieldRef = ref(null)
const controlRef = ref(null)
const inputRef = ref(null)
const sheetInputRef = ref(null)
const listboxRef = ref(null)
const isCompact = useCompactViewport()

const combo = useComboboxListbox({
  props,
  emit,
  isCompact,
  focusInput: () => inputRef.value?.focus(),
  listRef: () => listboxRef.value?.element,
})

const fieldBoundary = computed(() => fieldRef.value?.$el)
const isFilled = computed(
  () => combo.selectedOptions.value.length > 0 || combo.isOpen.value || Boolean(combo.query.value),
)
const activeDescendant = computed(() =>
  combo.isOpen.value && combo.activeIndex.value >= 0
    ? `${baseId}-option-${combo.activeIndex.value}`
    : undefined,
)
const comboboxAria = computed(() => ({
  role: 'combobox',
  'aria-autocomplete': 'list',
  'aria-expanded': combo.isOpen.value ? 'true' : 'false',
  'aria-controls': combo.isOpen.value ? listboxId : undefined,
}))
const doneLabel = computed(() => {
  const count = combo.selectedValues.value.length
  return t('components.dropdown.done', { count }, count)
})

function optionId(index) {
  return `${baseId}-option-${index}`
}

function onControlClick(event) {
  if (props.disabled || event.target.closest('button')) return
  inputRef.value?.focus()
  if (isCompact.value) combo.open()
  else combo.toggle()
}

function onRemove(option) {
  combo.remove(option)
  inputRef.value?.focus()
}

function onFocus(event) {
  if (!props.multiple && !isCompact.value) event.target.select()
}

function onBlur(event) {
  if (!combo.isOpen.value) emit('blur', event)
}

watch(
  () => combo.isOpen.value && isCompact.value,
  async (sheetOpen) => {
    if (!sheetOpen) return
    await nextTick()
    sheetInputRef.value?.focus()
  },
)
</script>

<template>
  <FormField
    ref="fieldRef"
    v-slot="{ id, describedBy, invalid }"
    :label="label"
    :name="name || baseId"
    :error="error"
    :hint="hint"
    :required="required"
    :disabled="disabled"
    :filled="isFilled"
  >
    <div
      ref="controlRef"
      class="field__control dropdown-combobox"
      :class="{ 'is-open': combo.isOpen.value, 'is-disabled': disabled }"
      @click="onControlClick"
    >
      <template v-if="multiple">
        <DropdownTag
          v-for="option in combo.selectedOptions.value"
          :key="valueKey(option.value)"
          :label="option.label"
          :disabled="disabled"
          @remove="onRemove(option)"
        />
      </template>
      <input
        :id="id"
        ref="inputRef"
        class="dropdown-combobox__input"
        type="text"
        autocomplete="off"
        :name="name || undefined"
        :value="combo.inputText.value"
        :placeholder="placeholder || ' '"
        :readonly="isCompact"
        :disabled="disabled"
        v-bind="comboboxAria"
        :aria-haspopup="isCompact ? 'dialog' : 'listbox'"
        :aria-activedescendant="isCompact ? undefined : activeDescendant"
        :aria-describedby="describedBy"
        :aria-invalid="invalid || undefined"
        :aria-required="required || undefined"
        @input="combo.onInput"
        @keydown="combo.onKeydown"
        @focus="onFocus"
        @blur="onBlur"
      />
      <AppIcon
        :name="combo.isOpen.value ? 'chevron-up' : 'chevron-down'"
        class="dropdown-combobox__chevron"
      />
    </div>
    <DropdownSurface
      :open="combo.isOpen.value"
      :anchor="controlRef"
      :boundary="fieldBoundary"
      :placement="DROPDOWN_PLACEMENTS.field"
      :sheet="isCompact"
      :title="label"
      @dismiss="combo.close"
    >
      <div v-if="isCompact" class="dropdown-sheet__search">
        <AppIcon name="search" class="dropdown-sheet__search-icon" />
        <input
          ref="sheetInputRef"
          class="dropdown-sheet__search-input"
          type="text"
          autocomplete="off"
          :value="combo.query.value"
          :aria-label="t('components.dropdown.search', { label })"
          :placeholder="t('components.dropdown.searchPlaceholder')"
          v-bind="comboboxAria"
          :aria-activedescendant="activeDescendant"
          @input="combo.onInput"
          @keydown="combo.onKeydown"
        />
      </div>
      <div
        v-if="isCompact && multiple && combo.selectedOptions.value.length"
        class="dropdown-sheet__tags"
      >
        <DropdownTag
          v-for="option in combo.selectedOptions.value"
          :key="valueKey(option.value)"
          :label="option.label"
          @remove="combo.remove(option)"
        />
      </div>
      <DropdownListbox
        :id="listboxId"
        ref="listboxRef"
        :label="label"
        :options="combo.visibleOptions.value"
        :option-id="optionId"
        :active-index="combo.activeIndex.value"
        :selected-values="combo.selectedValues.value"
        :multiple="multiple"
        :loading="loading"
        :query="combo.query.value"
        @choose="combo.choose"
        @hover="combo.hover"
      />
      <template v-if="isCompact && multiple" #footer>
        <BaseButton variant="primary" class="dropdown-sheet__done" @click="combo.close">{{
          doneLabel
        }}</BaseButton>
      </template>
    </DropdownSurface>
  </FormField>
</template>
