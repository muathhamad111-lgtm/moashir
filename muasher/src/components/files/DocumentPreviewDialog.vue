<script setup>
import BaseModal from '@/components/modal/BaseModal.vue'
import DocumentViewer from './DocumentViewer.vue'

defineProps({
  modelValue: { type: Boolean, required: true },
  file: { type: Object, default: null },
  label: { type: String, default: '' },
  requestLink: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <BaseModal
    :model-value="modelValue && Boolean(file)"
    :title="label"
    size="xl"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <DocumentViewer v-if="file" :file="file" :label="label" :request-link="requestLink" />
  </BaseModal>
</template>
