<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldRadio from '@/components/form/FieldRadio.vue'
import FieldPercent from '@/components/form/FieldPercent.vue'
import { useEntityOptions } from '@/composables/useEntityOptions'
import { GOVERNANCE_GRADE } from '@/constants/entityEnums'

const { t } = useI18n()
const { strategicPlanOptions } = useEntityOptions()
</script>

<template>
  <fieldset class="entity-form__group">
    <legend class="entity-form__legend">{{ t('entities.sections.metrics') }}</legend>
    <div class="entity-form__grid">
      <FieldPercent
        name="governanceGrade"
        :label="t('entities.fields.governanceGrade')"
        :hint="t('entities.hints.governanceGrade')"
        :integer-digits="GOVERNANCE_GRADE.INTEGER_DIGITS"
        :decimals="GOVERNANCE_GRADE.DECIMALS"
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
    </div>
    <FieldRadio
      name="hasStrategicPlan"
      :label="t('entities.fields.hasStrategicPlan')"
      :options="strategicPlanOptions"
    />
  </fieldset>
</template>
