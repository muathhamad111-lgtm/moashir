<script setup>
import { onMounted, useId } from 'vue'
import { useField } from 'vee-validate'
import SearchSelect from '@/components/form/SearchSelect.vue'
import { useEntityPicker } from '@/composables/useEntityPicker'

defineProps({
  label: { type: String, required: true },
  placeholder: { type: String, default: '' },
})

const errorId = useId()
const { value, errorMessage } = useField('entityId')
const { options, loading, search } = useEntityPicker()

onMounted(() => search())
</script>

<template>
  <div class="field" :class="{ 'field--error': errorMessage }">
    <SearchSelect
      v-model="value"
      remote
      :options="options"
      :loading="loading"
      :label="label"
      :placeholder="placeholder"
      :aria-describedby="errorMessage ? errorId : undefined"
      @search="search"
    />
    <p v-if="errorMessage" :id="errorId" class="field__error" role="alert">{{ errorMessage }}</p>
  </div>
</template>
