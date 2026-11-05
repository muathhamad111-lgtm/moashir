<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'

const { t } = useI18n()
</script>

<template>
  <fieldset class="entity-form__group">
    <legend class="entity-form__legend">{{ t('entities.sections.identity') }}</legend>
    <div class="entity-form__grid">
      <FieldInput name="name.ar" input-dir="rtl" :label="t('entities.fields.nameAr')" required />
      <FieldInput
        name="name.en"
        input-dir="ltr"
        :label="t('entities.fields.nameEn')"
        :hint="t('entities.hints.nameEn')"
      />
      <FieldInput
        name="authorizedPersonName"
        :label="t('entities.fields.authorizedPersonName')"
        autocomplete="name"
        required
      />
      <FieldInput
        name="phone"
        type="tel"
        input-dir="ltr"
        inputmode="tel"
        autocomplete="tel"
        :label="t('entities.fields.phone')"
        :hint="t('entities.hints.phone')"
      />
    </div>
  </fieldset>
</template>
