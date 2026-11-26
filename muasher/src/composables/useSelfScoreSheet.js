import { computed } from 'vue'
import { useForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import {
  scoreFieldName,
  sheetFieldErrors,
  sheetPayload,
  sheetValues,
  unscoredUnits,
} from '@/utils/assessments/scoreSheet'
import { scoreSheetSchema } from '@/utils/assessments/scoreSheetSchema'
import { unitScoringType } from '@/utils/assessments/scoringTypes'

export function useSelfScoreSheet({ form, units }) {
  const { t } = useI18n()
  const scale = computed(() => form.value?.scale ?? null)
  const schema = computed(() => scoreSheetSchema(t, units.value, scale.value))
  const typeOf = (node) => unitScoringType(node, scale.value)
  const sheet = useForm({ validationSchema: schema, keepValuesOnUnmount: true })

  const dirty = computed(() => sheet.meta.value.dirty)

  function resetFrom(scores) {
    sheet.resetForm({ values: sheetValues(scores, units.value) })
  }

  function payload() {
    return sheetPayload(sheet.values, units.value)
  }

  function missing() {
    return unscoredUnits(sheet.values, units.value)
  }

  function markMissing(missingUnits) {
    const message = t('assessment.self.validation.required')
    sheet.setErrors(Object.fromEntries(missingUnits.map((unit) => [scoreFieldName(unit), message])))
  }

  function applyServerErrors(fieldErrors) {
    sheet.setErrors(sheetFieldErrors(fieldErrors))
  }

  async function isValid() {
    const { valid } = await sheet.validate()
    return valid
  }

  return {
    sheet,
    typeOf,
    dirty,
    resetFrom,
    payload,
    missing,
    markMissing,
    applyServerErrors,
    isValid,
  }
}
