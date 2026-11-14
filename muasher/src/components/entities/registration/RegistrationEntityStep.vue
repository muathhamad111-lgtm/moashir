<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import FieldPercent from '@/components/form/FieldPercent.vue'
import EntityUnifiedNumberField from '@/components/entities/form/EntityUnifiedNumberField.vue'
import { useEntityOptions } from '@/composables/useEntityOptions'
import { useRegionCityFields } from '@/composables/useRegionCityFields'
import { GOVERNANCE_GRADE } from '@/constants/entityEnums'

const props = defineProps({
  regionOptions: { type: Array, required: true },
  cityOptionsFor: { type: Function, required: true },
})

const { t } = useI18n()
const { entityTypeOptions, sectorOptions, orgSizeOptions } = useEntityOptions()
const { regionId, cityOptions } = useRegionCityFields(props.cityOptionsFor)
</script>

<template>
  <div class="entity-form__grid">
    <FieldSelect
      name="entityType"
      :label="t('entities.fields.entityType')"
      :options="entityTypeOptions"
      required
    />
    <EntityUnifiedNumberField />
    <FieldInput name="name.ar" input-dir="rtl" :label="t('entities.fields.nameAr')" required />
    <FieldInput name="name.en" input-dir="ltr" :label="t('entities.fields.nameEn')" required />
    <FieldSelect
      name="regionId"
      :label="t('entities.fields.region')"
      :options="regionOptions"
      required
    />
    <FieldSelect
      name="cityId"
      :label="t('entities.fields.city')"
      :options="cityOptions"
      :disabled="!regionId"
      required
    />
    <FieldSelect
      name="developmentSector"
      :label="t('entities.fields.developmentSector')"
      :options="sectorOptions"
      required
    />
    <FieldSelect
      name="orgSize"
      :label="t('entities.fields.orgSize')"
      :options="orgSizeOptions"
      required
    />
    <FieldInput
      name="foundingYear"
      inputmode="numeric"
      :label="t('entities.fields.foundingYear')"
      required
    />
    <FieldInput
      name="employeesCount"
      inputmode="numeric"
      :label="t('entities.fields.employeesCount')"
    />
    <FieldPercent
      name="governanceGrade"
      :label="t('entities.fields.governanceGrade')"
      :integer-digits="GOVERNANCE_GRADE.INTEGER_DIGITS"
      :decimals="GOVERNANCE_GRADE.DECIMALS"
    />
    <FieldInput
      name="annualBeneficiariesCount"
      inputmode="numeric"
      :label="t('entities.fields.annualBeneficiariesCount')"
    />
  </div>
</template>
