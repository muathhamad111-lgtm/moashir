<script setup>
import { toRef } from 'vue'
import { useField } from 'vee-validate'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  description: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const { value, errorMessage } = useField(toRef(props, 'name'))
</script>

<template>
  <div class="field" :class="{ 'field--error': errorMessage }">
    <ToggleSwitch
      :model-value="value === true"
      :name="name"
      :label="label"
      :description="description"
      :disabled="disabled"
      @update:model-value="value = $event"
    />
    <p v-if="errorMessage" class="field__error" role="alert">{{ errorMessage }}</p>
  </div>
</template>
