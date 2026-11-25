<script setup>
import { computed } from 'vue'
import { useFormValues } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import FieldRadio from '@/components/form/FieldRadio.vue'
import FieldPercent from '@/components/form/FieldPercent.vue'
import { useEntityOptions } from '@/composables/useEntityOptions'
import { GOVERNANCE_GRADE } from '@/constants/entityEnums'

defineProps({
  hint: { type: String, default: '' },
})

const { t } = useI18n()
const { strategicPlanOptions } = useEntityOptions()
const values = useFormValues()
const hasGrade = computed(() => values.value.hasGovernanceGrade === 'yes')
</script>

<template>
  <FieldRadio
    class="entity-form__wide"
    name="hasGovernanceGrade"
    :label="t('entities.fields.hasGovernanceGrade')"
    :options="strategicPlanOptions"
    required
  />
  <FieldPercent
    v-if="hasGrade"
    name="governanceGrade"
    :label="t('entities.fields.governanceGrade')"
    :hint="hint"
    :integer-digits="GOVERNANCE_GRADE.INTEGER_DIGITS"
    :decimals="GOVERNANCE_GRADE.DECIMALS"
    required
  />
</template>
