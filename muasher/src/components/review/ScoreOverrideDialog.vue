<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import { useToast } from '@/composables/useToast'
import { assessmentOverridesService } from '@/services/assessmentOverridesService'
import { ACCREDITED_LIMITS } from '@/constants/accredited'
import { SCORE_LEVELS } from '@/constants/assessment'
import {
  isRawScore,
  normalizedRawValue,
  sameRawValue,
  savedScoreOf,
  scoreFieldName,
  sheetFieldErrors,
  withinScale,
} from '@/utils/assessments/scoreSheet'
import { logger } from '@/utils/logger'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  assessmentId: { type: Number, required: true },
  units: { type: Array, required: true },
  scores: { type: Object, required: true },
  maximum: { type: String, required: true },
})

const emit = defineEmits(['update:modelValue', 'done'])
const { t } = useI18n()
const toast = useToast()
const values = reactive({})
const errors = ref({})
const reason = ref('')
const busy = ref(false)

const changed = computed(() =>
  props.units.filter(
    (unit) =>
      !sameRawValue(values[scoreFieldName(unit)], savedScoreOf(props.scores, unit)?.rawValue ?? ''),
  ),
)

function validate() {
  const next = {}
  changed.value.forEach((unit) => {
    const value = values[scoreFieldName(unit)]
    if (!isRawScore(value) || !withinScale(value, props.maximum))
      next[scoreFieldName(unit)] = t('assessment.self.validation.range', { max: props.maximum })
  })
  if (!changed.value.length) next.form = t('review.override.noChange')
  const text = reason.value.trim()
  if (
    text.length < ACCREDITED_LIMITS.OVERRIDE_REASON_MIN ||
    text.length > ACCREDITED_LIMITS.REASON_MAX
  )
    next.reason = t('validation.minChars', { min: ACCREDITED_LIMITS.OVERRIDE_REASON_MIN })
  errors.value = next
  return !Object.keys(next).length
}

function payload() {
  const scores = { subs: {}, points: {} }
  changed.value.forEach((unit) => {
    scores[unit.level === SCORE_LEVELS.SUB ? 'subs' : 'points'][String(unit.id)] =
      normalizedRawValue(values[scoreFieldName(unit)])
  })
  return { scores, reason: reason.value.trim() }
}

async function onSubmit() {
  if (busy.value || !validate()) return
  busy.value = true
  logger.breadcrumb('form', 'overrides.scores.submit', { fields: ['scores', 'reason'] })
  try {
    const { data } = await assessmentOverridesService.overrideScores(props.assessmentId, payload())
    toast.success(t('review.override.done'))
    emit('update:modelValue', false)
    emit('done', data)
  } catch (error) {
    errors.value = {
      ...sheetFieldErrors(error?.normalized?.fieldErrors),
      reason: error?.normalized?.fieldErrors?.reason?.[0] ?? '',
    }
    toast.error(error?.normalized?.message || t('errors.unexpected'))
  } finally {
    busy.value = false
  }
}

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    props.units.forEach((unit) => {
      values[scoreFieldName(unit)] = savedScoreOf(props.scores, unit)?.rawValue ?? ''
    })
    reason.value = ''
    errors.value = {}
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    size="lg"
    :title="t('review.override.title')"
    :busy="busy"
    :submit-label="t('review.override.submit')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="onSubmit"
  >
    <div class="override-dialog">
      <p class="override-dialog__warning" role="note">
        <AppIcon name="warning-triangle" />
        {{ t('review.override.warning') }}
      </p>
      <div class="override-dialog__grid">
        <BaseInput
          v-for="unit in units"
          :key="scoreFieldName(unit)"
          v-model="values[scoreFieldName(unit)]"
          :name="`override-${scoreFieldName(unit)}`"
          :label="t('review.override.itemLabel', { code: unit.code, max: maximum })"
          :error="errors[scoreFieldName(unit)]"
          inputmode="decimal"
          input-dir="ltr"
        />
      </div>
      <p v-if="errors.form" class="field__error" role="alert">{{ errors.form }}</p>
      <BaseTextarea
        v-model="reason"
        name="override-reason"
        :label="t('requests.overrides.reason')"
        :hint="t('entities.reason.hint', { min: ACCREDITED_LIMITS.OVERRIDE_REASON_MIN })"
        :error="errors.reason"
        required
      />
    </div>
  </FormModal>
</template>
