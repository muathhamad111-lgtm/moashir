<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import LocalizedFields from '@/components/cms/LocalizedFields.vue'
import { KPI_STATUS_KEYS } from '@/constants/kpis'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  types: { type: Array, default: () => [] },
  localized: { type: Function, required: true },
})

const { t } = useI18n()

function optionsOf(entries) {
  return [
    { value: '', label: t('kpis.admin.form.none') },
    ...entries.map((entry) => ({
      value: entry.id,
      label: entry.active
        ? props.localized(entry.name)
        : t('kpis.admin.form.inactiveOption', { name: props.localized(entry.name) }),
    })),
  ]
}

const categoryOptions = computed(() => optionsOf(props.categories))
const typeOptions = computed(() => optionsOf(props.types))
const statusOptions = computed(() => [
  { value: '', label: t('kpis.admin.form.none') },
  ...KPI_STATUS_KEYS.map((key) => ({ value: key, label: t(`kpis.statuses.${key}`) })),
])
</script>

<template>
  <fieldset class="kpi-form__section">
    <legend class="kpi-form__legend">{{ t('kpis.admin.form.identityGroup') }}</legend>
    <LocalizedFields name="name" :label="t('kpis.admin.fields.name')" required />
    <div class="kpi-form__row">
      <FieldInput
        name="slug"
        :label="t('kpis.admin.fields.slug')"
        :hint="t('kpis.admin.fields.slugHint')"
        input-dir="ltr"
      />
      <FieldInput
        name="measurementYear"
        type="number"
        inputmode="numeric"
        :label="t('kpis.admin.fields.measurementYear')"
        input-dir="ltr"
      />
    </div>
    <div class="kpi-form__row">
      <FieldSelect
        name="kpiCategoryId"
        :label="t('kpis.admin.fields.category')"
        :options="categoryOptions"
      />
      <FieldSelect name="kpiTypeId" :label="t('kpis.admin.fields.type')" :options="typeOptions" />
    </div>
    <FieldSelect name="status" :label="t('kpis.admin.fields.status')" :options="statusOptions" />
  </fieldset>
</template>
