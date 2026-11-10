<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { object, string } from 'yup'
import FormModal from '@/components/modal/FormModal.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useAuthStore } from '@/stores/auth'
import { filteringService } from '@/services/filteringService'
import { AWARD_LIMITS } from '@/constants/advanced'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  mode: { type: String, required: true },
  cycleId: { type: Number, required: true },
  row: { type: Object, required: true },
  threshold: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'done'])
const { t } = useI18n()
const auth = useAuthStore()

const isQualify = computed(() => props.mode === 'qualify')
const isBelow = computed(() => isQualify.value && props.row.meetsThreshold === false)
const asksStaffReason = computed(() => isQualify.value && !isBelow.value && auth.isSuperAdmin)
const EMPTY = { note: '', overrideReason: '', reason: '' }

function lengthRule(min, required) {
  const base = string()
    .trim()
    .max(
      AWARD_LIMITS.REASON_MAX,
      t('entities.validation.maxChars', { max: AWARD_LIMITS.REASON_MAX }),
    )
  const ranged = base.test(
    'min',
    t('validation.minChars', { min }),
    (value) => !value || value.length >= min,
  )
  return required ? ranged.required(t('validation.required')) : ranged
}

const schema = computed(() =>
  object({
    note: string()
      .trim()
      .max(
        AWARD_LIMITS.NOTE_MAX,
        t('entities.validation.maxChars', { max: AWARD_LIMITS.NOTE_MAX }),
      ),
    overrideReason: isBelow.value ? lengthRule(AWARD_LIMITS.OVERRIDE_REASON_MIN, true) : string(),
    reason: isQualify.value
      ? lengthRule(AWARD_LIMITS.REASON_MIN, asksStaffReason.value)
      : lengthRule(AWARD_LIMITS.REASON_MIN, true),
  }),
)

function payloadOf(values) {
  const note = values.note.trim()
  const base = note ? { note } : {}
  if (!isQualify.value) return { ...base, reason: values.reason.trim() }
  const extra = isBelow.value ? { overrideReason: values.overrideReason.trim() } : {}
  return { ...base, ...extra, ...(values.reason.trim() ? { reason: values.reason.trim() } : {}) }
}

const form = useValidatedForm({
  schema,
  initialValues: { ...EMPTY },
  action: 'filtering.decide',
  submit: async (values) => {
    const send = isQualify.value ? filteringService.qualify : filteringService.disqualify
    const { data } = await send(props.cycleId, props.row.id, payloadOf(values))
    emit('update:modelValue', false)
    emit('done', data)
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: { ...EMPTY } })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="t(`filtering.decision.${mode}.title`)"
    :subtitle="row.number"
    :busy="form.submitting.value"
    :submit-label="t(`filtering.decision.${mode}.submit`)"
    :submit-variant="isQualify ? 'primary' : 'danger'"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="filtering-decision">
      <p v-if="isBelow" class="filtering-decision__warning" role="alert">
        <AppIcon name="warning-triangle" />
        {{
          t('filtering.decision.belowWarning', {
            score: row.initialResult?.percent ?? '',
            threshold,
          })
        }}
      </p>
      <FieldTextarea
        v-if="isBelow"
        name="overrideReason"
        :label="t('filtering.decision.overrideReason')"
        :hint="t('entities.reason.hint', { min: AWARD_LIMITS.OVERRIDE_REASON_MIN })"
        :rows="4"
        required
      />
      <FieldTextarea
        v-if="!isQualify || asksStaffReason"
        name="reason"
        :label="isQualify ? t('filtering.decision.staffReason') : t('filtering.decision.reason')"
        :hint="t('entities.reason.hint', { min: AWARD_LIMITS.REASON_MIN })"
        :rows="4"
        required
      />
      <FieldTextarea
        name="note"
        :label="t('filtering.decision.note')"
        :hint="t('filtering.decision.noteHint', { max: AWARD_LIMITS.NOTE_MAX })"
        :rows="3"
      />
    </div>
  </FormModal>
</template>
