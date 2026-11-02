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

const DEFAULT_MAXIMUM = '10.00'

export function useSelfScoreSheet({ form, units }) {
  const { t } = useI18n()
  const maximum = computed(() => form.value?.scale?.maximum ?? DEFAULT_MAXIMUM)
  const schema = computed(() => scoreSheetSchema(t, units.value, maximum.value))
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

  return { sheet, maximum, dirty, resetFrom, payload, missing, markMissing, applyServerErrors, isValid }
}
