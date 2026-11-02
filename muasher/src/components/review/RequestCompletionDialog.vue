<script setup>
import { computed, reactive, watch } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import FieldRadio from '@/components/form/FieldRadio.vue'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { assessorReviewService } from '@/services/assessorReviewService'
import { ACCREDITED_LIMITS, EXPIRY_OUTCOMES } from '@/constants/accredited'
import { completionPayload, completionSchema, deadlineBounds, itemOptions } from '@/utils/accredited/completionForm'
import { localizedText } from '@/utils/localizedText'
import { formatRiyadhDate } from '@/utils/datetime'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  assessmentId: { type: Number, required: true },
  criteria: { type: Object, required: true },
  askReason: { type: Function, required: true },
  send: { type: Function, default: null },
  bounds: { type: Object, default: null },
  variant: { type: String, default: 'assessor' },
})

const emit = defineEmits(['update:modelValue', 'done'])
const { t, locale } = useI18n()
const bounds = props.bounds ?? deadlineBounds()
const copy = props.variant === 'follower' ? 'requests.return' : 'review.completion'
const maxLabel = computed(() => formatRiyadhDate(`${bounds.max}T12:00:00+03:00`, locale.value))
const deadlineErrorKey = props.variant === 'follower' ? 'requests.return.deadlineError' : 'review.completion.validation.deadline'
const notes = reactive({})

const form = useValidatedForm({
  schema: computed(() => completionSchema(t, bounds, t(deadlineErrorKey, { days: ACCREDITED_LIMITS.COMPLETION_DEADLINE_MAX_DAYS, date: maxLabel.value }))),
  initialValues: { notes: '', deadline: '', expiryOutcome: EXPIRY_OUTCOMES.CONTINUE, items: [] },
  action: 'review.requestCompletion',
  submit: async (values) => {
    const reason = await props.askReason()
    const payload = { ...completionPayload(values, notes), ...(reason ? { reason } : {}) }
    const send = props.send ?? assessorReviewService.requestCompletion
    const { data } = await send(props.assessmentId, payload)
    emit('update:modelValue', false)
    emit('done', data)
  },
})
const { value: items, errorMessage: itemsError } = useField('items')
const { value: deadline, errorMessage: deadlineError } = useField('deadline')

const groups = computed(() => {
  const options = itemOptions(props.criteria)
  return props.criteria.mains.map((main) => ({ main, options: options.filter((option) => main.subs.some((sub) => sub.id === option.sub.id)) }))
})
const outcomeOptions = computed(() => Object.values(EXPIRY_OUTCOMES).map((value) => ({ value, label: t(`accredited.completion.outcomes.${value}`) })))

function toggle(key, checked) {
  const others = (items.value ?? []).filter((item) => item !== key)
  items.value = checked ? [...others, key] : others
}

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    Object.keys(notes).forEach((key) => delete notes[key])
    form.resetForm({ values: { notes: '', deadline: '', expiryOutcome: EXPIRY_OUTCOMES.CONTINUE, items: [] } })
  },
)
</script>

<template>
  <FormModal :model-value="modelValue" size="lg" :title="t(`${copy}.title`)" :busy="form.submitting.value" :submit-label="t(`${copy}.submit`)" @update:model-value="emit('update:modelValue', $event)" @submit="form.onSubmit">
    <div class="completion-dialog">
      <p class="completion-dialog__lead">{{ t(`${copy}.lead`) }}</p>
      <FieldTextarea name="notes" :label="t('review.completion.notes')" :hint="t('review.completion.notesHint', { min: ACCREDITED_LIMITS.COMPLETION_NOTES_MIN, max: ACCREDITED_LIMITS.COMPLETION_NOTES_MAX })" :rows="4" required />
      <fieldset class="completion-dialog__items" :aria-describedby="itemsError ? 'completion-items-error' : undefined">
        <legend class="completion-dialog__legend">{{ t('review.completion.items') }}</legend>
        <div v-for="group in groups" :key="group.main.id" class="completion-dialog__group">
          <p class="completion-dialog__main">{{ group.main.code }} {{ localizedText(group.main.name, locale) }}</p>
          <div v-for="option in group.options" :key="option.key" class="completion-dialog__option">
            <BaseCheckbox
              :name="`item-${option.key}`"
              :label="t('review.completion.itemLabel', { code: option.sub.code, kind: t(`accredited.completion.kinds.${option.kind}`) })"
              :model-value="(items ?? []).includes(option.key)"
              @update:model-value="toggle(option.key, $event)"
            />
            <BaseInput v-if="(items ?? []).includes(option.key)" v-model="notes[option.key]" :name="`note-${option.key}`" :label="t('review.completion.itemNote')" :hint="t('review.completion.itemNoteHint', { max: ACCREDITED_LIMITS.COMPLETION_ITEM_NOTE_MAX })" />
          </div>
        </div>
        <p v-if="itemsError" id="completion-items-error" class="field__error" role="alert">{{ itemsError }}</p>
      </fieldset>
      <BaseDatePicker v-model="deadline" name="deadline" :label="t('review.completion.deadline')" :min="bounds.min" :max="bounds.max" :hint="t(`${copy}.deadlineHint`, { days: ACCREDITED_LIMITS.COMPLETION_DEADLINE_MAX_DAYS, date: maxLabel })" :error="deadlineError" required />
      <FieldRadio name="expiryOutcome" :label="t('review.completion.expiryOutcome')" :options="outcomeOptions" />
      <p class="completion-dialog__outcome-hint" role="note">{{ t('review.completion.expiryHint') }}</p>
    </div>
  </FormModal>
</template>
