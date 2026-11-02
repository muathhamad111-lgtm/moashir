<script setup>
import { computed, useId } from 'vue'

defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, required: true },
  name: { type: String, required: true },
  description: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const controlId = useId()
const descId = computed(() => `${controlId}-desc`)
</script>

<template>
  <div class="toggle-switch" :class="{ 'is-disabled': disabled }">
    <div class="toggle-switch__text">
      <label class="toggle-switch__label" :for="controlId">{{ label }}</label>
      <p v-if="description" :id="descId" class="toggle-switch__desc">{{ description }}</p>
    </div>
    <label class="toggle-switch__track">
      <input
        :id="controlId"
        class="toggle-switch__input"
        type="checkbox"
        role="switch"
        :name="name"
        :checked="modelValue"
        :disabled="disabled"
        :aria-checked="modelValue"
        :aria-describedby="description ? descId : undefined"
        @change="emit('update:modelValue', $event.target.checked)"
      />
      <span class="toggle-switch__thumb" aria-hidden="true"></span>
    </label>
  </div>
</template>
