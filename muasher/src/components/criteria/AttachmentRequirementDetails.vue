<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { EVIDENCE_TYPE_EXTENSIONS } from '@/constants/accredited'

const props = defineProps({
  item: { type: Object, required: true },
  index: { type: Number, required: true },
  errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['change'])
const { t } = useI18n()
const TYPES = Object.keys(EVIDENCE_TYPE_EXTENSIONS)

const guidance = computed(() => ({
  ar: props.item.guidance?.ar ?? '',
  en: props.item.guidance?.en ?? '',
}))
const allowedTypes = computed(() => props.item.allowedTypes ?? [])
const hasErrors = computed(() =>
  ['guidanceAr', 'guidanceEn', 'allowedTypes', 'maxFiles'].some((field) => props.errors[field]),
)

function setGuidance(locale, value) {
  emit('change', { guidance: { ...guidance.value, [locale]: value } })
}

function toggleType(type, checked) {
  const others = allowedTypes.value.filter((value) => value !== type)
  emit('change', { allowedTypes: checked ? [...others, type] : others })
}
</script>

<template>
  <details class="attachments-editor__details" :open="hasErrors || undefined">
    <summary>{{ t('criteria.attachments.details') }}</summary>
    <div class="attachments-editor__names">
      <BaseTextarea
        :model-value="guidance.ar"
        dir="rtl"
        :name="`attachmentGuidanceAr${index}`"
        :rows="2"
        :label="t('criteria.attachments.guidanceAr')"
        :error="errors.guidanceAr"
        @update:model-value="setGuidance('ar', $event)"
      />
      <BaseTextarea
        :model-value="guidance.en"
        dir="ltr"
        :name="`attachmentGuidanceEn${index}`"
        :rows="2"
        :label="t('criteria.attachments.guidanceEn')"
        :error="errors.guidanceEn"
        @update:model-value="setGuidance('en', $event)"
      />
    </div>
    <fieldset class="attachments-editor__types">
      <legend class="field__label">{{ t('criteria.attachments.types') }}</legend>
      <label v-for="type in TYPES" :key="type" class="checkbox">
        <span class="checkbox__box">
          <input
            class="checkbox__input"
            type="checkbox"
            :name="`attachmentTypes${index}`"
            :value="type"
            :checked="allowedTypes.includes(type)"
            @change="toggleType(type, $event.target.checked)"
          />
          <AppIcon v-if="allowedTypes.includes(type)" name="check" class="checkbox__check" />
        </span>
        <span class="checkbox__label">{{ t(`settings.assessment.types.${type}`) }}</span>
      </label>
      <p v-if="errors.allowedTypes" class="field__error" role="alert">{{ errors.allowedTypes }}</p>
      <p v-else class="attachments-editor__hint">{{ t('criteria.attachments.typesHint') }}</p>
    </fieldset>
    <BaseInput
      :model-value="item.maxFiles ?? ''"
      :name="`attachmentMaxFiles${index}`"
      inputmode="numeric"
      input-dir="ltr"
      :label="t('criteria.attachments.maxFiles')"
      :hint="t('criteria.attachments.maxFilesHint')"
      :error="errors.maxFiles"
      @update:model-value="emit('change', { maxFiles: String($event).replace(/\D/g, '') })"
    />
  </details>
</template>
