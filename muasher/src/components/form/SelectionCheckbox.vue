<script setup>
import { useId } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  modelValue: { type: Boolean, default: false },
  indeterminate: { type: Boolean, default: false },
  label: { type: String, required: true },
  showLabel: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const controlId = useId()
</script>

<template>
  <label
    class="checkbox selection-checkbox"
    :class="{ 'is-disabled': disabled, 'selection-checkbox--labelled': showLabel }"
    :for="controlId"
  >
    <span class="checkbox__box">
      <input
        :id="controlId"
        class="checkbox__input"
        type="checkbox"
        :checked="modelValue"
        :indeterminate.prop="indeterminate"
        :disabled="disabled"
        @change="emit('update:modelValue', $event.target.checked)"
      />
      <AppIcon v-if="modelValue" name="check" class="checkbox__check" />
      <AppIcon v-else-if="indeterminate" name="minus" class="checkbox__check" />
    </span>
    <span :class="showLabel ? 'checkbox__label' : 'selection-checkbox__label'">{{ label }}</span>
  </label>
</template>
