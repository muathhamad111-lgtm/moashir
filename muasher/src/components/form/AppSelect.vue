<script setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCompactViewport } from '@/composables/useCompactViewport'
import { useSelectListbox } from '@/composables/useSelectListbox'
import { DROPDOWN_PLACEMENTS, SELECT_VARIANTS } from '@/constants/dropdown'
import { sameValue, valueKey } from '@/utils/dropdown/optionValue'
import FormField from '@/components/form/FormField.vue'
import DropdownSurface from '@/components/ui/DropdownSurface.vue'
import DropdownListbox from '@/components/ui/DropdownListbox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: [String, Number, null], default: null },
  options: { type: Array, required: true },
  label: { type: String, required: true },
  variant: {
    type: String,
    default: SELECT_VARIANTS.field,
    validator: (value) => Object.values(SELECT_VARIANTS).includes(value),
  },
  name: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  hint: { type: String, default: '' },
  error: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  hideLabel: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'open', 'close', 'blur'])

const { t } = useI18n()
const baseId = useId()
const listboxId = `${baseId}-listbox`
const labelId = `${baseId}-label`
const triggerRef = ref(null)
const fieldRef = ref(null)
const filterRef = ref(null)
const listboxRef = ref(null)
const isCompact = useCompactViewport()
const isField = computed(() => props.variant === SELECT_VARIANTS.field)

const select = useSelectListbox({
  props,
  emit,
  triggerRef,
  listRef: () => listboxRef.value?.element,
  isCompact,
  commitsOnTab: isField,
})

const fieldBoundary = computed(() => fieldRef.value?.$el)
const valueText = computed(
  () => select.selectedOption.value?.label ?? (props.placeholder || t('common.select')),
)
const isApplied = computed(
  () => Boolean(props.options.length) && !sameValue(props.options[0].value, props.modelValue),
)
const activeDescendant = computed(() =>
  select.isOpen.value && select.activeIndex.value >= 0
    ? optionId(select.activeIndex.value)
    : undefined,
)
const triggerAria = computed(() => ({
  'data-value': select.selectedOption.value ? valueKey(props.modelValue) : undefined,
  role: 'combobox',
  'aria-haspopup': isCompact.value ? 'dialog' : 'listbox',
  'aria-expanded': select.isOpen.value ? 'true' : 'false',
  'aria-controls': select.isOpen.value ? listboxId : undefined,
  'aria-activedescendant': isCompact.value ? undefined : activeDescendant.value,
}))

const surfaceProps = computed(() => ({
  open: select.isOpen.value,
  anchor: triggerRef.value,
  sheet: isCompact.value,
  title: props.label,
}))
const listboxProps = computed(() => ({
  id: listboxId,
  label: props.label,
  options: props.options,
  optionId,
  activeIndex: select.activeIndex.value,
  selectedValues: select.selectedOption.value ? [props.modelValue] : [],
  loading: props.loading,
  tabindex: isCompact.value ? 0 : undefined,
  'aria-activedescendant': isCompact.value ? activeDescendant.value : undefined,
}))
const listboxEvents = {
  keydown: select.onKeydown,
  choose: select.choose,
  hover: select.hover,
}

function optionId(index) {
  return `${baseId}-option-${index}`
}

function onBlur(event) {
  if (!select.isOpen.value) emit('blur', event)
}
</script>

<template>
  <FormField
    v-if="isField"
    ref="fieldRef"
    v-slot="{ id, describedBy, invalid }"
    :label="label"
    :name="name || baseId"
    :error="error"
    :hint="hint"
    :required="required"
    :disabled="disabled"
    :filled="Boolean(select.selectedOption.value) || select.isOpen.value"
    :class="{ 'field--label-hidden': hideLabel }"
  >
    <button
      :id="id"
      ref="triggerRef"
      type="button"
      class="field__control dropdown-field"
      :name="name || undefined"
      :disabled="disabled"
      v-bind="triggerAria"
      :aria-describedby="describedBy"
      :aria-invalid="invalid || undefined"
      :aria-required="required || undefined"
      @click="select.toggle"
      @keydown="select.onKeydown"
      @blur="onBlur"
    >
      <span
        class="dropdown-field__value"
        :class="{ 'is-placeholder': !select.selectedOption.value }"
        >{{ valueText }}</span
      >
      <AppIcon
        :name="select.isOpen.value ? 'chevron-up' : 'chevron-down'"
        class="dropdown-field__chevron"
      />
    </button>
    <DropdownSurface
      v-bind="surfaceProps"
      :boundary="fieldBoundary"
      :placement="DROPDOWN_PLACEMENTS.field"
      @dismiss="select.close"
    >
      <DropdownListbox ref="listboxRef" v-bind="listboxProps" v-on="listboxEvents" />
    </DropdownSurface>
  </FormField>
  <div
    v-else
    ref="filterRef"
    class="dropdown-filter"
    :class="{ 'is-applied': isApplied, 'is-disabled': disabled }"
  >
    <button
      ref="triggerRef"
      type="button"
      class="dropdown-filter__trigger"
      :name="name || undefined"
      :disabled="disabled"
      v-bind="triggerAria"
      :aria-labelledby="labelId"
      @click="select.toggle"
      @keydown="select.onKeydown"
      @blur="onBlur"
    >
      <span class="dropdown-filter__label" aria-hidden="true"
        ><span :id="labelId">{{ label }}</span
        >{{ t('components.dropdown.labelSeparator') }}</span
      >
      <span class="dropdown-filter__value">{{ valueText }}</span>
      <AppIcon
        :name="select.isOpen.value ? 'chevron-sm-up' : 'chevron-sm-down'"
        class="dropdown-filter__chevron"
      />
    </button>
    <DropdownSurface
      v-bind="surfaceProps"
      :boundary="filterRef"
      :placement="DROPDOWN_PLACEMENTS.filter"
      @dismiss="select.close"
    >
      <DropdownListbox ref="listboxRef" v-bind="listboxProps" v-on="listboxEvents" />
    </DropdownSurface>
  </div>
</template>
