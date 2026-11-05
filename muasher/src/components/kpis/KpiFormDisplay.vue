<script setup>
import { useI18n } from 'vue-i18n'
import { useField } from 'vee-validate'
import FieldIconSelect from '@/components/form/FieldIconSelect.vue'
import FieldToggle from '@/components/form/FieldToggle.vue'
import ContentImageField from '@/components/cms/ContentImageField.vue'
import KpiColorField from './KpiColorField.vue'
import { KPI_ICON_OPTIONS } from '@/constants/kpis'
import { kpisAdminService } from '@/services/kpisAdminService'

defineProps({
  disabled: { type: Boolean, default: false },
})

const { t } = useI18n()
const { value: image } = useField('image')
const upload = (file) => kpisAdminService.uploadImage(file)
</script>

<template>
  <fieldset class="kpi-form__section">
    <legend class="kpi-form__legend">{{ t('kpis.admin.form.displayGroup') }}</legend>
    <FieldIconSelect
      name="icon"
      :label="t('kpis.admin.fields.icon')"
      :options="KPI_ICON_OPTIONS"
      :disabled="disabled"
    />
    <ContentImageField
      v-model="image"
      :upload="upload"
      :label="t('kpis.admin.fields.image')"
      name="kpiImage"
      :disabled="disabled"
    />
    <KpiColorField name="cardColorToken" :label="t('kpis.admin.fields.cardColor')" />
    <KpiColorField name="badgeColorToken" :label="t('kpis.admin.fields.badgeColor')" />
    <FieldToggle
      name="showOnHomepage"
      :label="t('kpis.admin.fields.showOnHomepage')"
      :description="t('kpis.admin.fields.showOnHomepageHint')"
    />
  </fieldset>
</template>
