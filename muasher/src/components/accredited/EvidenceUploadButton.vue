<script setup>
import { ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  label: { type: String, required: true },
  accept: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  icon: { type: String, default: 'fa-solid fa-cloud-arrow-up' },
})

const emit = defineEmits(['select'])
const input = ref(null)

function onChange(event) {
  const [file] = Array.from(event.target.files ?? [])
  event.target.value = ''
  if (file) emit('select', file)
}
</script>

<template>
  <span class="evidence-upload">
    <input ref="input" type="file" class="evidence-upload__input" :accept="accept || undefined" :disabled="disabled" hidden @change="onChange" />
    <BaseButton variant="secondary" size="sm" :icon="icon" :loading="loading" :disabled="disabled" @click="input?.click()">
      {{ label }}
    </BaseButton>
  </span>
</template>
