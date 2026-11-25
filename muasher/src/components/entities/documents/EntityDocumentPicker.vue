<script setup>
import { useTemplateRef } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { ENTITY_DOCUMENT_RULES } from '@/constants/entityDocuments'

defineProps({
  label: { type: String, required: true },
  name: { type: String, required: true },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['select'])
const input = useTemplateRef('input')

function onChange(event) {
  const [file] = Array.from(event.target.files ?? [])
  if (file) emit('select', file)
  event.target.value = ''
}
</script>

<template>
  <input
    ref="input"
    type="file"
    :name="name"
    :accept="ENTITY_DOCUMENT_RULES.accept"
    :disabled="disabled"
    hidden
    @change="onChange"
  />
  <BaseButton
    variant="secondary"
    size="sm"
    icon="upload"
    :loading="loading"
    :disabled="disabled"
    @click="input?.click()"
  >
    {{ label }}
  </BaseButton>
</template>
