<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import LocalizedFields from '@/components/cms/LocalizedFields.vue'

const { t } = useI18n()
</script>

<template>
  <fieldset class="kpi-form__section">
    <legend class="kpi-form__legend">{{ t('kpis.admin.form.descriptionGroup') }}</legend>
    <LocalizedFields
      name="shortDescription"
      :label="t('kpis.admin.fields.shortDescription')"
      multiline
    />
    <LocalizedFields
      name="fullDescription"
      :label="t('kpis.admin.fields.fullDescription')"
      multiline
    />
  </fieldset>
  <fieldset class="kpi-form__section">
    <legend class="kpi-form__legend">{{ t('kpis.admin.form.ownershipGroup') }}</legend>
    <LocalizedFields name="owner" :label="t('kpis.admin.fields.owner')" />
    <LocalizedFields name="dataSource" :label="t('kpis.admin.fields.dataSource')" />
    <FieldInput
      name="externalLink"
      type="url"
      :label="t('kpis.admin.fields.externalLink')"
      :hint="t('kpis.admin.fields.externalLinkHint')"
      input-dir="ltr"
    />
  </fieldset>
  <fieldset class="kpi-form__section">
    <legend class="kpi-form__legend">{{ t('kpis.admin.form.methodGroup') }}</legend>
    <LocalizedFields name="calcMethod" :label="t('kpis.admin.fields.calcMethod')" multiline />
    <LocalizedFields name="notes" :label="t('kpis.admin.fields.notes')" multiline />
  </fieldset>
</template>
