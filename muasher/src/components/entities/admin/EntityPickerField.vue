<script setup>
import { onMounted } from 'vue'
import { useField } from 'vee-validate'
import AppCombobox from '@/components/form/AppCombobox.vue'
import { useEntityPicker } from '@/composables/useEntityPicker'

defineProps({
  label: { type: String, required: true },
  placeholder: { type: String, default: '' },
})

const { value, errorMessage, handleBlur } = useField('entityId')
const { options, loading, search } = useEntityPicker()

onMounted(() => search())
</script>

<template>
  <AppCombobox
    v-model="value"
    remote
    name="entityId"
    :options="options"
    :loading="loading"
    :label="label"
    :placeholder="placeholder"
    :error="errorMessage"
    required
    @search="search"
    @blur="handleBlur"
  />
</template>
