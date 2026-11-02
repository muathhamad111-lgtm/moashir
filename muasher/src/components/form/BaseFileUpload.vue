<script setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

const props = defineProps({
  files: { type: Array, default: () => [] },
  label: { type: String, required: true },
  name: { type: String, required: true },
  accept: { type: String, default: '' },
  multiple: { type: Boolean, default: false },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'remove', 'retry'])

const { t } = useI18n()
const inputRef = ref(null)
const controlId = useId()
const errorId = computed(() => `${controlId}-error`)

const classes = computed(() => [
  'field',
  'file-upload',
  props.error && 'field--error',
  props.disabled && 'field--disabled',
  props.required && 'field--required',
])

function openPicker() {
  if (props.disabled) return
  inputRef.value?.click()
}

function onInputChange(event) {
  const selected = Array.from(event.target.files ?? [])
  if (selected.length) emit('select', selected)
  event.target.value = ''
}

function statusLabel(file) {
  if (file.status === 'uploading') return t('components.fileUpload.uploading', { progress: file.progress ?? 0 })
  if (file.status === 'failed') return t('components.fileUpload.failed')
  return t('components.fileUpload.done')
}
</script>

<template>
  <div :class="classes">
    <span class="field__label" :id="`${controlId}-label`">{{ label }}</span>
    <input
      :id="controlId"
      ref="inputRef"
      type="file"
      :name="name"
      :accept="accept || undefined"
      :multiple="multiple"
      :disabled="disabled"
      hidden
      :aria-describedby="error ? errorId : undefined"
      @change="onInputChange"
    />
    <button
      type="button"
      class="file-upload__dropzone"
      :disabled="disabled"
      :aria-labelledby="`${controlId}-label`"
      @click="openPicker"
    >
      <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
      <span>{{ t('components.fileUpload.choose') }}</span>
      <span v-if="hint" class="file-upload__hint">{{ hint }}</span>
    </button>
    <ul v-if="files.length" class="file-upload__list">
      <li
        v-for="file in files"
        :key="file.id"
        class="file-upload__item"
        :class="{ 'is-failed': file.status === 'failed' }"
      >
        <i class="fa-solid fa-file-lines file-upload__file-icon" aria-hidden="true"></i>
        <div class="file-upload__meta">
          <span class="file-upload__name">{{ file.name }}</span>
          <span class="file-upload__status">{{ statusLabel(file) }}</span>
          <progress
            v-if="file.status === 'uploading'"
            class="file-upload__progress"
            :value="file.progress ?? 0"
            max="100"
            :aria-label="t('components.fileUpload.progressLabel', { name: file.name })"
          ></progress>
        </div>
        <BaseButton
          v-if="file.status === 'failed'"
          variant="ghost"
          size="sm"
          icon-only
          icon="fa-solid fa-rotate-right"
          :aria-label="t('components.fileUpload.retry', { name: file.name })"
          @click="emit('retry', file.id)"
        />
        <BaseButton
          variant="ghost"
          size="sm"
          icon-only
          icon="fa-solid fa-trash-can"
          :aria-label="t('components.fileUpload.remove', { name: file.name })"
          @click="emit('remove', file.id)"
        />
      </li>
    </ul>
    <p v-if="error" :id="errorId" class="field__error" role="alert">{{ error }}</p>
  </div>
</template>
