<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'

defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  multiline: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  hint: { type: String, default: '' },
})

const LANGUAGES = ['ar', 'en']
const { t } = useI18n()
</script>

<template>
  <div class="localized-fields">
    <template v-for="language in LANGUAGES" :key="language">
      <FieldTextarea
        v-if="multiline"
        :name="`${name}.${language}`"
        :label="t('cms.fields.inLanguage', { label, language: t(`cms.languages.${language}`) })"
        :hint="hint"
        :required="required"
        :rows="3"
      />
      <FieldInput
        v-else
        :name="`${name}.${language}`"
        :label="t('cms.fields.inLanguage', { label, language: t(`cms.languages.${language}`) })"
        :hint="hint"
        :required="required"
        :input-dir="language === 'ar' ? 'rtl' : 'ltr'"
      />
    </template>
  </div>
</template>
