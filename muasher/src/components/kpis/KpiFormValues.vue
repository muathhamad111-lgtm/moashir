<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import LocalizedFields from '@/components/cms/LocalizedFields.vue'

defineProps({
  editing: { type: Boolean, default: false },
})

const { t } = useI18n()
const NUMBER_FIELDS = ['value', 'target', 'baseline']
</script>

<template>
  <fieldset class="kpi-form__section">
    <legend class="kpi-form__legend">{{ t('kpis.admin.form.valueGroup') }}</legend>
    <div class="kpi-form__row">
      <FieldInput
        v-for="field in NUMBER_FIELDS"
        :key="field"
        :name="field"
        inputmode="decimal"
        :label="t(`kpis.admin.fields.${field}`)"
        :hint="field === 'value' && editing ? t('kpis.admin.fields.valueHint') : ''"
        input-dir="ltr"
      />
    </div>
    <LocalizedFields name="unit" :label="t('kpis.admin.fields.unit')" />
  </fieldset>
</template>
