<script setup>
import { computed } from 'vue'
import { useField, useSetFieldValue } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import FieldPercent from '@/components/form/FieldPercent.vue'
import { GOVERNANCE_GRADE } from '@/constants/entityEnums'

defineProps({
  hint: { type: String, default: '' },
})

// One required grade with «no grade» beneath it (operator 2026-11-26); the answer it sets keeps the API's
// has_governance_grade: ticked = false, unticked = true with the grade required.
const NO = 'no'
const YES = 'yes'

const { t } = useI18n()
const { value: answer } = useField('hasGovernanceGrade')
const setGrade = useSetFieldValue('governanceGrade')
const hasNoGrade = computed(() => answer.value === NO)

function markNoGrade(checked) {
  answer.value = checked ? NO : YES
  if (checked) setGrade('')
}
</script>

<template>
  <div class="governance-grade">
    <FieldPercent
      name="governanceGrade"
      :label="t('entities.fields.governanceGrade')"
      :hint="hint"
      :integer-digits="GOVERNANCE_GRADE.INTEGER_DIGITS"
      :decimals="GOVERNANCE_GRADE.DECIMALS"
      :disabled="hasNoGrade"
      :required="!hasNoGrade"
    />
    <BaseCheckbox
      class="governance-grade__none"
      name="noGovernanceGrade"
      :model-value="hasNoGrade"
      :label="t('entities.fields.noGovernanceGrade')"
      @update:model-value="markNoGrade"
    />
  </div>
</template>
