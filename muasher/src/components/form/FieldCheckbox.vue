<script setup>
import { toRef } from 'vue'
import { useField } from 'vee-validate'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
})

const { value, errorMessage } = useField(toRef(props, 'name'))
</script>

<template>
  <BaseCheckbox
    :model-value="value === true"
    :name="name"
    :label="label"
    :error="errorMessage"
    @update:model-value="value = $event"
  />
</template>
