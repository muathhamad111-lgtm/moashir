<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({ ar: '', en: '' }),
  },
  label: { type: String, default: '' },
  name: { type: String, required: true },
  placeholders: { type: Array, default: () => [] },
  rows: { type: Number, default: 4 },
  error: { type: Object, default: () => ({}) },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const { t, locale } = useI18n()

const arField = ref(null)
const enField = ref(null)
const activeLang = ref('ar')

const arValue = computed(() => props.modelValue?.ar ?? '')
const enValue = computed(() => props.modelValue?.en ?? '')

function update(lang, value) {
  emit('update:modelValue', { ...props.modelValue, [lang]: value })
}

function placeholderLabel(item) {
  const label = item.label
  if (!label) return item.token
  return label[locale.value] || label.ar || label.en || item.token
}

function fieldRef(lang) {
  return lang === 'ar' ? arField.value : enField.value
}

function insertPlaceholder(token) {
  if (props.disabled) return
  const lang = activeLang.value
  const el = fieldRef(lang)
  const current = lang === 'ar' ? arValue.value : enValue.value
  if (!el) {
    update(lang, `${current}${token}`)
    return
  }
  const start = el.selectionStart ?? current.length
  const end = el.selectionEnd ?? current.length
  const next = `${current.slice(0, start)}${token}${current.slice(end)}`
  update(lang, next)
  requestAnimationFrame(() => {
    el.focus()
    const caret = start + token.length
    el.setSelectionRange(caret, caret)
  })
}
</script>

<template>
  <fieldset class="template-editor" :disabled="disabled">
    <legend v-if="label" class="template-editor__legend">{{ label }}</legend>

    <div
      v-if="placeholders.length"
      class="template-editor__palette"
      role="group"
      :aria-label="t('settings.template.paletteLabel')"
    >
      <span class="template-editor__palette-label">{{ t('settings.template.insert') }}</span>
      <button
        v-for="item in placeholders"
        :key="item.token"
        type="button"
        class="template-editor__chip"
        :disabled="disabled"
        :title="placeholderLabel(item)"
        @click="insertPlaceholder(item.token)"
      >
        <code class="template-editor__chip-token" dir="ltr">{{ item.token }}</code>
        <span class="template-editor__chip-label">{{ placeholderLabel(item) }}</span>
      </button>
    </div>

    <div class="template-editor__fields">
      <div class="field template-editor__field">
        <label class="field__label" :for="`${name}-ar`">
          {{ t('settings.template.arLabel') }}
        </label>
        <textarea
          :id="`${name}-ar`"
          ref="arField"
          class="field__control"
          :name="`${name}-ar`"
          :rows="rows"
          dir="rtl"
          lang="ar"
          :value="arValue"
          :disabled="disabled"
          :aria-invalid="Boolean(error.ar) || undefined"
          @focus="activeLang = 'ar'"
          @input="update('ar', $event.target.value)"
        ></textarea>
        <slot name="field-footer" lang="ar" :value="arValue" />
        <p v-if="error.ar" class="field__error" role="alert">{{ error.ar }}</p>
      </div>

      <div class="field template-editor__field">
        <label class="field__label" :for="`${name}-en`">
          {{ t('settings.template.enLabel') }}
        </label>
        <textarea
          :id="`${name}-en`"
          ref="enField"
          class="field__control"
          :name="`${name}-en`"
          :rows="rows"
          dir="ltr"
          lang="en"
          :value="enValue"
          :disabled="disabled"
          :aria-invalid="Boolean(error.en) || undefined"
          @focus="activeLang = 'en'"
          @input="update('en', $event.target.value)"
        ></textarea>
        <slot name="field-footer" lang="en" :value="enValue" />
        <p v-if="error.en" class="field__error" role="alert">{{ error.en }}</p>
      </div>
    </div>
  </fieldset>
</template>
