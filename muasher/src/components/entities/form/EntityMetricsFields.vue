<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldRadio from '@/components/form/FieldRadio.vue'
import { useEntityOptions } from '@/composables/useEntityOptions'

defineProps({
  hint: { type: String, default: '' },
})

const { t } = useI18n()
const { strategicPlanOptions } = useEntityOptions()
</script>

<template>
  <fieldset class="entity-form__group">
    <legend class="entity-form__legend">{{ t('entities.sections.metrics') }}</legend>
    <p v-if="hint" class="entity-form__hint">{{ hint }}</p>
    <div class="entity-form__grid">
      <FieldInput
        name="governanceGrade"
        input-dir="ltr"
        inputmode="decimal"
        :label="t('entities.fields.governanceGrade')"
        :hint="t('entities.hints.governanceGrade')"
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
