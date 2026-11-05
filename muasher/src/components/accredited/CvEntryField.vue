<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import { ACCREDITED_LIMITS } from '@/constants/accredited'

const props = defineProps({
  subId: { type: Number, required: true },
  code: { type: String, required: true },
  modelValue: { type: String, default: '' },
  editable: { type: Boolean, default: false },
  missing: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const hint = computed(() =>
  t('accredited.cv.hint', { count: props.modelValue.length, max: ACCREDITED_LIMITS.CV_MAX }),
)
const error = computed(() => {
  if (props.modelValue.length > ACCREDITED_LIMITS.CV_MAX)
    return t('accredited.cv.tooLong', { max: ACCREDITED_LIMITS.CV_MAX })
  return props.missing && props.editable && !props.modelValue.trim()
    ? t('accredited.cv.required')
    : ''
})
</script>

<template>
  <div class="cv-entry">
    <BaseTextarea
      :model-value="modelValue"
      :name="`cv-${subId}`"
      :label="t('accredited.cv.label', { code })"
      :rows="5"
      :hint="hint"
      :error="error"
      :disabled="!editable"
      required
      @update:model-value="emit('update:modelValue', $event)"
    />
  </div>
</template>
