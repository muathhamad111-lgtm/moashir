<script setup>
import { computed, useId } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, required: true },
  name: { type: String, required: true },
  error: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const controlId = useId()
const errorId = computed(() => `${controlId}-error`)
</script>

<template>
  <div class="field" :class="{ 'field--error': error }">
    <label class="checkbox" :class="{ 'is-disabled': disabled }" :for="controlId">
      <span class="checkbox__box">
        <input
          :id="controlId"
          class="checkbox__input"
          type="checkbox"
          :name="name"
          :checked="modelValue"
          :disabled="disabled"
          :aria-invalid="Boolean(error) || undefined"
          :aria-describedby="error ? errorId : undefined"
          @change="emit('update:modelValue', $event.target.checked)"
        />
        <AppIcon v-if="modelValue" name="check" class="checkbox__check" />
      </span>
      <span class="checkbox__label">{{ label }}</span>
    </label>
    <p v-if="error" :id="errorId" class="field__error" role="alert">{{ error }}</p>
  </div>
</template>
