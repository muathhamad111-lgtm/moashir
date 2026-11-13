<script setup>
import { computed, useId } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  value: { type: String, required: true },
  name: { type: String, required: true },
  icon: { type: String, required: true },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  meta: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const controlId = useId()
const descriptionId = `${controlId}-description`
const checked = computed(() => props.modelValue === props.value)
</script>

<template>
  <label
    class="method-option"
    :class="{ 'is-selected': checked, 'is-disabled': disabled }"
    :for="controlId"
    :data-value="value"
  >
    <input
      :id="controlId"
      class="method-option__input"
      type="radio"
      :name="name"
      :value="value"
      :checked="checked"
      :disabled="disabled"
      :aria-describedby="description || meta.length ? descriptionId : undefined"
      @change="emit('update:modelValue', value)"
    />
    <span class="method-option__radio" aria-hidden="true"></span>
    <span class="method-option__icon" aria-hidden="true"><AppIcon :name="icon" /></span>
    <span class="method-option__text">
      <span class="method-option__title">{{ title }}</span>
      <span :id="descriptionId" class="method-option__description">
        <span v-if="description">{{ description }}</span>
        <span
          v-for="line in meta"
          :key="line.text"
          class="method-option__meta"
          :class="line.tone && `method-option__meta--${line.tone}`"
          >{{ line.text }}</span
        >
      </span>
    </span>
  </label>
</template>
