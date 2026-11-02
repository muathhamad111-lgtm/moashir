<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import { useEntityOptions } from '@/composables/useEntityOptions'

const { t } = useI18n()
const { entityTypeOptions, sectorOptions, orgSizeOptions } = useEntityOptions()
</script>

<template>
  <fieldset class="entity-form__group">
    <legend class="entity-form__legend">{{ t('entities.sections.classification') }}</legend>
    <div class="entity-form__grid">
      <FieldSelect name="entityType" :label="t('entities.fields.entityType')" :options="entityTypeOptions" required />
      <FieldSelect name="developmentSector" :label="t('entities.fields.developmentSector')" :options="sectorOptions" required />
      <FieldSelect name="orgSize" :label="t('entities.fields.orgSize')" :options="orgSizeOptions" required />
      <FieldInput name="foundingYear" inputmode="numeric" :label="t('entities.fields.foundingYear')" required />
    </div>
  </fieldset>
</template>
