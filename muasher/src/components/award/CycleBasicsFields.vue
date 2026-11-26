<script setup>
import { useI18n } from 'vue-i18n'
import CycleStepCounter from './CycleStepCounter.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'

defineProps({
  locked: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <fieldset class="cycle-form__section">
    <legend class="cycle-form__legend">
      {{ t('cycles.form.sections.basics') }}
      <CycleStepCounter />
    </legend>
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
    <p class="cycle-form__hint">{{ t('cycles.setup.identity.descriptionHint') }}</p>
    <div class="cycle-form__grid">
      <FieldTextarea
        name="description.ar"
        :label="t('cycles.setup.identity.descriptionAr')"
        :rows="5"
        input-dir="rtl"
        :disabled="locked.has('description')"
      />
      <FieldTextarea
        name="description.en"
        :label="t('cycles.setup.identity.descriptionEn')"
        :rows="5"
        input-dir="ltr"
        :disabled="locked.has('description')"
      />
    </div>
  </fieldset>
</template>
