<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import FieldDate from '@/components/form/FieldDate.vue'
import EntityUnifiedNumberField from '@/components/entities/form/EntityUnifiedNumberField.vue'
import EntityGovernanceGradeFields from '@/components/entities/form/EntityGovernanceGradeFields.vue'
import RegistrationDocumentField from './RegistrationDocumentField.vue'
import { useEntityOptions } from '@/composables/useEntityOptions'
import { useRegionCityFields } from '@/composables/useRegionCityFields'
import { EARLIEST_FOUNDING_DATE } from '@/constants/entityEnums'
import { riyadhDateKey } from '@/utils/datetime'

const props = defineProps({
  regionOptions: { type: Array, required: true },
  cityOptionsFor: { type: Function, required: true },
})

const { t } = useI18n()
const { entityTypeOptions, sectorOptions, orgSizeOptions } = useEntityOptions()
const { regionId, cityOptions } = useRegionCityFields(props.cityOptionsFor)
const today = riyadhDateKey(new Date())
</script>

<template>
  <div class="entity-form__grid">
    <FieldInput name="name.ar" input-dir="rtl" :label="t('entities.fields.nameAr')" required />
    <FieldInput name="name.en" input-dir="ltr" :label="t('entities.fields.nameEn')" required />
    <FieldSelect
      name="entityType"
      :label="t('entities.fields.entityType')"
      :options="entityTypeOptions"
      required
    />
    <EntityUnifiedNumberField />
    <FieldInput
      name="licenseNumber"
      inputmode="numeric"
      input-dir="ltr"
      :label="t('entities.fields.licenseNumber')"
      required
    />
    <RegistrationDocumentField
      class="registration-document--paired"
      name="licenseCertificate"
      :label="t('entities.documents.kinds.licenseCertificate')"
    />
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
    <FieldDate
      name="foundedOn"
      :label="t('entities.fields.foundedOn')"
      :min="EARLIEST_FOUNDING_DATE"
      :max="today"
      required
    />
    <FieldInput
      name="employeesCount"
      inputmode="numeric"
      :label="t('entities.fields.employeesCount')"
    />
    <FieldInput
      name="annualBeneficiariesCount"
      inputmode="numeric"
      :label="t('entities.fields.annualBeneficiariesCount')"
    />
    <EntityGovernanceGradeFields />
  </div>
</template>
