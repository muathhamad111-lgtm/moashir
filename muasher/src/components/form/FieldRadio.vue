<script setup>
import { toRef } from 'vue'
import { useField } from 'vee-validate'
import BaseRadio from '@/components/form/BaseRadio.vue'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  options: { type: Array, required: true },
})

const { value, errorMessage } = useField(toRef(props, 'name'))
</script>

<template>
  <BaseRadio v-model="value" :name="name" :label="label" :options="options" :error="errorMessage" inline />
</template>
