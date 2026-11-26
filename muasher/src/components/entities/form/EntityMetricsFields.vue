<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldRadio from '@/components/form/FieldRadio.vue'
import EntityGovernanceGradeFields from './EntityGovernanceGradeFields.vue'
import { useEntityOptions } from '@/composables/useEntityOptions'

const { t } = useI18n()
const { strategicPlanOptions } = useEntityOptions()
</script>

<template>
  <fieldset class="entity-form__group">
    <legend class="entity-form__legend">{{ t('entities.sections.metrics') }}</legend>
    <div class="entity-form__grid">
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
      <EntityGovernanceGradeFields :hint="t('entities.hints.governanceGrade')" />
    </div>
    <FieldRadio
      name="hasStrategicPlan"
      :label="t('entities.fields.hasStrategicPlan')"
      :options="strategicPlanOptions"
    />
  </fieldset>
</template>
