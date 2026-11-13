<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'

defineProps({
  locked: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <fieldset class="cycle-form__section">
    <legend class="cycle-form__legend">{{ t('cycles.form.sections.basics') }}</legend>
    <div class="cycle-form__grid">
      <FieldInput
        name="name.ar"
        :label="t('cycles.form.nameAr')"
        input-dir="rtl"
        required
        :disabled="locked.has('name')"
      />
      <FieldInput
        name="name.en"
        :label="t('cycles.form.nameEn')"
        input-dir="ltr"
        required
        :disabled="locked.has('name')"
      />
      <FieldInput
        name="year"
        :label="t('cycles.form.year')"
        inputmode="numeric"
        input-dir="ltr"
        :hint="t('cycles.form.yearHint')"
        :disabled="locked.has('year')"
      />
    </div>
  </fieldset>
</template>
